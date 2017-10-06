<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 13:01
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\DHL;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\Xml;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet';
    const URL_PRODUCTION = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';

    /**
     * @var ClientInterface
     */
    private $guzzle;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * DHL constructor.
     * @param ClientInterface $guzzle
     * @param Credentials $credentials
     * @param string $baseUrl
     */
    function __construct(ClientInterface $guzzle, Credentials $credentials, string $baseUrl = self::URL_PRODUCTION)
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface
    {
        $package = $request->package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        // after value conversions we might get lots of decimals. deal with that
        $length = number_format($package->length->getValue(), 2, '.', '');
        $width = number_format($package->width->getValue(), 2, '.', '');
        $height = number_format($package->height->getValue(), 2, '.', '');
        $weight = number_format($package->weight->getValue(), 2, '.', '');

        $sender = $request->sender;
        $recipient = $request->recipient;

        $getQuoteRequest = [
            'GetQuote' => [
                'Request' => [
                    'ServiceHeader' => [
                        'MessageTime' => $request->date->format('c'),
                        'SiteID' => $this->credentials->getSiteID(),
                        'Password' => $this->credentials->getPassword(),
                    ],
                ],
                'From' => [
                    'CountryCode' => $sender->countryCode,
                    'Postalcode' => $sender->zip,
                    'City' => Xml::cdata($sender->city),
                ],
                'BkgDetails' => [
                    'PaymentCountryCode' => $sender->countryCode,
                    'Date' => $request->date->format('Y-m-d'),
                    'ReadyTime' => 'PT00H00M',
                    'DimensionUnit' => 'CM',
                    'WeightUnit' => 'KG',
                    'Pieces' => [
                        'Piece' => [
                            [
                                'PieceID' => 1,
                                'Height' => $height,
                                'Depth' => $length,
                                'Width' => $width,
                                'Weight' => $weight,
                            ],
                        ],
                    ],
                    // we insert a null PaymentAccountNumber here so we can set
                    // a correct value when needed. keys in PHP arrays keep the
                    // position in which they are inserted and therefore we cannot
                    // insert this key later - that would cause DHL validation
                    // to fail.
                    'PaymentAccountNumber' => null,
                    'IsDutiable' => $request->isDutiable ? 'Y' : 'N',
                    // same as above
                    'QtdShp' => [],
                ],
                'To' => [
                    'CountryCode' => $recipient->countryCode,
                    'Postalcode' => $recipient->zip,
                    'City' => Xml::cdata($recipient->city),
                ],
                'Dutiable' => [
                    'DeclaredCurrency' => $request->currency,
                    'DeclaredValue' => number_format($request->value, 2, '.', ''),
                ],
            ],
        ];

        // if we have any generic extra fields, set them here.
        foreach ($request->extra as $key => $value) {
            Arrays::set($getQuoteRequest, $key, $value);
        }

        foreach ($request->specialServices as $key => $service) {
            // magic
            Arrays::set($getQuoteRequest, "GetQuote.BkgDetails.QtdShp.QtdShpExChrg.$key.SpecialServiceType", $service);
        }

        $getQuoteRequest = Xml::removeKeysWithEmptyValues($getQuoteRequest);
        $getQuoteRequest = Xml::fromArray($getQuoteRequest);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<p:DCTRequest xmlns:p="http://www.dhl.com"
    xmlns:p1="http://www.dhl.com/datatypes"
    xmlns:p2="http://www.dhl.com/DCTRequestdatatypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.dhl.com DCT-req.xsd ">
   {$getQuoteRequest}
</p:DCTRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl, [
            'query' => [
                'isUTF8Support' => true,
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $qtdShip = $xml->xpath('/res:DCTResponse/GetQuoteResponse/BkgDetails/QtdShp');

            if (count($qtdShip) === 0) {
                $this->throwError($body);
            }

            $qtdShip =  new Collection($qtdShip);

            // somestimes the DHL api responds with a correct response
            // without ShippingCharge values which is strange.
            return $qtdShip->filter(function (SimpleXMLElement $element): bool {
                $charge = (string) $element->{'ShippingCharge'};
                return $charge !== '';
            })->map(function (SimpleXMLElement $element): Quote {
                $amountString = (string) $element->{'ShippingCharge'};

                // the amount is a decimal string, deal with that
                $amount = (int) round(((float)$amountString) * pow(10, 2));

                $product = (string) $element->{'ProductShortName'};

                return new Quote('DHL', $product, new Money($amount, new Currency((string) $element->{'CurrencyCode'})));
            })->value();
        });
    }

    /**
     * @param string $trackingNumber
     * @param array $options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
    {
        $trackRequest = Xml::fromArray([
            'Request' => [
                'ServiceHeader' => [
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
            ],
            'LanguageCode' => 'en',
            'AWBNumber' => $trackingNumber,
            'LevelOfDetails' => 'ALL_CHECK_POINTS',
            'PiecesEnabled' => 'S',
        ]);
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:KnownTrackingRequest xmlns:req="http://www.dhl.com"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.dhl.com TrackingRequestKnown.xsd">
    {$trackRequest}
</req:KnownTrackingRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl, [
            'query' => [
                'isUTF8Support' => true,
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);

            // previously we were using "ShipmentInfo[ShipmentEvent]" to determine if
            // the track was successful. it turns out some labels do not have shipment
            // events (especially when they're newly created). instead let's check if
            // the product code exists, which should hopefully be accurate.
            $info = $xml->xpath('/req:TrackingResponse/AWBInfo/ShipmentInfo[GlobalProductCode]');

            if (!$info) {
                $this->throwError($body);
            }

            $activities = (new Collection($info[0]->xpath('ShipmentEvent')))->map(function (SimpleXMLElement $element) {
                $dtString = ((string) $element->{'Date'}) . ' ' . ((string) $element->{'Time'});
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dtString);

                // ServiceArea.Description is a string of format {CITY} - {COUNTRY}
                $addressParts = explode(' - ', (string) $element->{'ServiceArea'}->{'Description'});

                $address = new Address('', [], '', $addressParts[0] ?? '', '', $addressParts[1] ?? '');

                // the description will sometimes include the location too.
                $description = (string) $element->{'ServiceEvent'}->{'Description'};

                $status = $this->getStatusFromEventCode((string) $element->{'ServiceEvent'}->{'EventCode'});

                return new TrackingActivity($status, $description, $dt, $address);
            })->reverse()->value(); // DHL orders the events in ascending order, we want the most recent first.

            return new Tracking('DHL', (string) $info[0]->{'GlobalProductCode'}, $activities);
        });
    }

    /**
     * @param string $code
     * @return int
     */
    private function getStatusFromEventCode(string $code): int
    {
        $code = mb_strtoupper($code, 'utf-8');

        // status mappings stolen from keeptracker.
        // DHL doesn't really provide any documentation for the
        // meaning of these so we'll just have to wing it for now.
        $codeMap = [
            TrackingActivity::STATUS_DELIVERED => [
                'CC', 'BR', 'TP', 'DD', 'OK', 'DL', 'DM',
            ],
            TrackingActivity::STATUS_EXCEPTION => [
                'BL', 'HI', 'HO', 'AD', 'SP', 'IA', 'SI', 'ST', 'NA',
                'CI', 'CU', 'LX', 'DI', 'SF', 'LV', 'UV', 'HN', 'DP',
                'PY', 'PM', 'BA', 'CD', 'UD', 'HX', 'TD', 'CA', 'NH',
                'MX', 'SS', 'CS', 'CM', 'RD', 'RR', 'MS', 'MC', 'OH',
                'SC', 'WX',

                // returned to shipper
                'RT',
            ],
        ];

        foreach ($codeMap as $status => $codes) {
            if (in_array($code, $codes)) {
                return $status;
            }
        }

        return TrackingActivity::STATUS_IN_TRANSIT;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $now = date('c');
        $package = $request->package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        $countryNames = require __DIR__ . '/../../countries.php';

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now,
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
            ],
            'LanguageCode' => 'en',
            'PiecesEnabled' => 'Y',
            'Billing' => [
                'ShipperAccountNumber' => $this->credentials->getAccountNumber(),
                'ShippingPaymentType' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_RECIPIENT ?
                    'R' : 'S',
                'BillingAccountNumber' => null,
                'DutyPaymentType' => $request->dutyPaymentType === ShipmentRequest::PAYMENT_TYPE_RECIPIENT ?
                    'R' : 'S',
                'DutyAccountNumber' => null,
            ],
            'Consignee' => [
                'CompanyName' => Xml::cdata($request->recipient->name),
                'AddressLine' => array_map([Xml::class, 'cdata'], $request->recipient->lines),
                'City' => Xml::cdata($request->recipient->city),
                'PostalCode' => $request->recipient->zip,
                'CountryCode' => $request->recipient->countryCode,
                'CountryName' => $countryNames[$request->recipient->countryCode],
                'Contact' => [
                    'PersonName' => Xml::cdata($request->recipient->contactName),
                    'PhoneNumber' => $request->recipient->contactPhone,
                ],
            ],
            'Dutiable' => [
                'DeclaredValue' => number_format($request->value, 2, '.', ''),
                'DeclaredCurrency' => $request->currency,
            ],
            'ExportDeclaration' => [
                'ExportLineItem' => array_map(function (int $key, ExportDeclaration $decl): array {
                    return [
                        'LineNumber' => $key + 1,
                        'Quantity' => $decl->quantity,
                        'QuantityUnit' => 'Piece',
                        'Description' => $decl->description,
                        'Value' => number_format($decl->value, 2, '.', ''),
                        'Weight' => [
                            'Weight' => $decl->weight,
                            'WeightUnit' => $decl->weight->getUnit() == Unit::POUND ? 'L' : 'K',
                        ],
                        'ManufactureCountryCode' => $decl->originCountryCode,
                    ];
                }, array_keys($request->exportDeclarations), $request->exportDeclarations),
            ],
            'Reference' => [
                'ReferenceID' => $request->reference,
            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => 1,
                'Pieces' => [
                    'Piece' => [
                        [
                            'PieceID' => 1,
                            'PackageType' => 'YP',
                            'Weight' => $package->weight,
                            'Width' => $package->width,
                            'Height' => $package->height,
                            'Depth' => $package->length,
                        ]
                    ],
                ],
                'Weight' => $package->weight,
                'WeightUnit' => 'K',
                'GlobalProductCode' => $request->service,
                'Date' => $request->date->format('Y-m-d'),
                'Contents' => $request->contents,
                //'DoorTo' => 'DD',
                'DimensionUnit' => 'C',
                'IsDutiable' => $request->isDutiable ? 'Y' : 'N',
                'CurrencyCode' => $request->currency,
            ],
            'Shipper' => [
                'ShipperID' => $this->credentials->getAccountNumber(),
                'CompanyName' => Xml::cdata($request->sender->name),
                'AddressLine' => array_map([Xml::class, 'cdata'], $request->sender->lines),
                'City' => Xml::cdata($request->sender->city),
                'PostalCode' => $request->sender->zip,
                'CountryCode' => $request->sender->countryCode,
                'CountryName' => $countryNames[$request->sender->countryCode],
                'Contact' => [
                    'PersonName' => Xml::cdata($request->sender->contactName),
                    'PhoneNumber' => $request->sender->contactPhone,
                ],
            ],
            'SpecialService' => array_map(function (string $service): array {
                return [
                    'SpecialServiceType' => $service,
                ];
            }, $request->specialServices),
            'LabelImageFormat' => 'PDF',
        ];

        if ($request->isDutiable && $request->incoterm) {
            $data['Dutiable']['TermsOfTrade'] = $request->incoterm;
        }

        foreach ($request->extra as $key => $value) {
            Arrays::set($data, $key, $value);
        }

        $data = Xml::removeKeysWithEmptyValues($data);
        $shipmentRequest = Xml::fromArray($data);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="5.0">
{$shipmentRequest}
</req:ShipmentRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl, [
            'query' => [
                'isUTF8Support' => true,
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string) $response->getBody();

            // yes, it is ridiculous to parse xml with a regex.
            // we're doing it here because SimpleXML seems to have
            // issues with non-latin characters in the DHL response
            // eg. ÅÄÖ.
            // Also, http://stackoverflow.com/a/1732454 :)
            if (preg_match('/<AirwayBillNumber>(.+)<\/AirwayBillNumber>/', $body, $matches) === 0) {
                $this->throwError($body);
            }

            $number = $matches[1];

            preg_match('/<OutputImage>(.+)<\/OutputImage>/', $body, $matches);

            $data = base64_decode($matches[1]);

            return new Shipment($number, 'DHL', $data, $body);
        });
    }

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        return new FulfilledPromise(true);
    }

    /**
     * @param string $body
     * @throws ServiceException
     */
    protected function throwError(string $body)
    {
        $errors = [];

        // extract the status part of the body because
        // we don't want to break simplexml with weird strings from DHL
        preg_match('/<Status>.+<\/Status>/s', $body, $matches);

        if (strpos($body, '<ActionStatus>Error</ActionStatus>') !== false) {
            $xml = new SimpleXMLElement($body);
            $arrayed = Xml::toArray($xml);
            $error = htmlspecialchars_decode(
                Arrays::get($arrayed, 'Response.Status.Condition.ConditionData')
            );
            $error = preg_replace('/\s+/', ' ', $error);
            $errors[] = $error;
        }

        throw new ServiceException($errors, $body);
    }

}
