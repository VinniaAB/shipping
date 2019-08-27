<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 13:01
 */
declare(strict_types=1);

namespace Vinnia\Shipping\DHL;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use LogicException;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\Pickup;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ErrorFormatterInterface;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\ExactErrorFormatter;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\TrackingResult;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;
use Vinnia\Util\Text\Xml;
use DOMDocument;
use DOMNode;
use Vinnia\Util\Text\XMLCallbackParser;

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
     * @var null|ErrorFormatterInterface
     */
    private $errorFormatter;

    /**
     * DHL constructor.
     * @param ClientInterface $guzzle
     * @param Credentials $credentials
     * @param string $baseUrl
     */
    function __construct(
        ClientInterface $guzzle,
        Credentials $credentials,
        string $baseUrl = self::URL_PRODUCTION,
        ?ErrorFormatterInterface $responseFormatter = null
    )
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->baseUrl = $baseUrl;
        $this->errorFormatter = $responseFormatter === null ?
            new ExactErrorFormatter() :
            $responseFormatter;
    }

    protected function getQuoteOrCapability(QuoteRequest $request, string $elementName): PromiseInterface
    {
        $parcels = array_map(function (Parcel $parcel, int $idx) use ($request): array {
            $p = $request->units == ShipmentRequest::UNITS_IMPERIAL ?
                $parcel->convertTo(Unit::INCH, Unit::POUND) :
                $parcel->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return [
                'PieceID' => $idx + 1,
                'Height' => $p->height->format(0),
                'Depth' => $p->length->format(0),
                'Width' => $p->width->format(0),
                'Weight' => $p->weight->format(2),
            ];
        }, $request->parcels, array_keys($request->parcels));

        $sender = $request->sender;
        $recipient = $request->recipient;

        $getQuoteRequest = [
            $elementName => [
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
                    'City' => $sender->city,
                ],
                'BkgDetails' => [
                    'PaymentCountryCode' => $sender->countryCode,
                    'Date' => $request->date->format('Y-m-d'),
                    'ReadyTime' => 'PT00H00M',
                    'DimensionUnit' => $request->units == QuoteRequest::UNITS_IMPERIAL ? 'IN' : 'CM',
                    'WeightUnit' => $request->units == QuoteRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                    'Pieces' => [
                        'Piece' => $parcels,
                    ],
                    'PaymentAccountNumber' => $this->credentials->getAccountNumber(),
                    'IsDutiable' => $request->isDutiable ? 'Y' : 'N',
                    // same as above
                    'QtdShp' => [],
                ],
                'To' => [
                    'CountryCode' => $recipient->countryCode,
                    'Postalcode' => $recipient->zip,
                    'City' => $recipient->city,
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

        $getQuoteRequest = removeKeysWithValues($getQuoteRequest, [], null);
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
        ]);
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface
    {
        return $this->getQuoteOrCapability($request, 'GetQuote')->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $xml = new DOMDocument('1.0', 'utf-8');
            $xml->loadXML($body, LIBXML_PARSEHUGE);

            $arrayed = Xml::toArray($xml);
            $qtdShip = Arrays::get($arrayed, 'GetQuoteResponse.BkgDetails.QtdShp');

            if (!$qtdShip) {
                $this->throwError($body);
            }

            $qtdShip = new Collection($qtdShip);

            // somestimes the DHL api responds with a correct response
            // without ShippingCharge values which is strange.
            return $qtdShip->filter(function (array $element): bool {
                return ((string) $element['ShippingCharge']) !== '';
            })->map(function (array $element): Quote {
                $amountString = (string)$element['ShippingCharge'];

                // the amount is a decimal string, deal with that
                $amount = (int) round(((float)$amountString) * pow(10, 2));
                $product = (string) $element['GlobalProductCode'];

                // 2019-08-27: it seems DHL sometimes decides to not
                // return a currency code. weird, huh.
                $money = new Money($amount, new Currency($element['CurrencyCode'] ?? 'EUR'));

                return new Quote('DHL', $product, $money);
            })->value();
        });
    }

    /**
     * @inheritdoc
     */
    public function getTrackingStatus(array $trackingNumbers, array $options = []): PromiseInterface
    {
        if (count($trackingNumbers) > 10) {
            throw new LogicException("DHL only allows tracking of 10 shipments at a time.");
        }

        $trackRequest = Xml::fromArray([
            'Request' => [
                'ServiceHeader' => [
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
            ],
            'LanguageCode' => 'en',
            'AWBNumber' => $trackingNumbers,
            'LevelOfDetails' => 'ALL_CHECK_POINTS',
            'PiecesEnabled' => 'B',
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
            $info = $xml->xpath('/req:TrackingResponse/AWBInfo');

            return array_map(function (SimpleXMLElement $element) use ($body): TrackingResult {
                $info = $element->xpath('ShipmentInfo[GlobalProductCode]');

                $trackingNo = (string) $element->AWBNumber;

                if (!$info) {
                    return new TrackingResult(TrackingResult::STATUS_ERROR, $trackingNo, $body);
                }

                $estimatedDelivery = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $info[0]->{'EstDlvyDateUTC'}, new DateTimeZone('UTC'));

                // createFromFormat returns false when parsing fails.
                // we don't want any booleans in our result.
                $estimatedDelivery = $estimatedDelivery ?: null;

                $activities = (new Collection($info[0]->xpath('ShipmentEvent')))->map(function (SimpleXMLElement $element) {
                    $dtString = ((string)$element->{'Date'}) . ' ' . ((string)$element->{'Time'});
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dtString);

                    // ServiceArea.Description is a string of format {CITY} - {COUNTRY}
                    $addressParts = explode(' - ', (string)$element->{'ServiceArea'}->{'Description'});

                    $address = new Address('', [], '', $addressParts[0] ?? '', '', $addressParts[1] ?? '');

                    // the description will sometimes include the location too.
                    $description = (string)$element->{'ServiceEvent'}->{'Description'};

                    $status = $this->getStatusFromEventCode((string)$element->{'ServiceEvent'}->{'EventCode'});

                    // Append signature to description
                    if (strpos($description, 'Signed for by') !== false) {
                        $description .= ': ' . ((string)$element->{'Signatory'} ?: 'Not provided');
                    }

                    return new TrackingActivity($status, $description, $dt, $address);
                })->value();

                $pieceInfos = Arrays::get(Xml::toArray($element), 'Pieces.PieceInfo');

                if (!Arrays::isNumericKeyArray($pieceInfos)) {
                    $pieceInfos = [$pieceInfos];
                }

                $parcels = array_map(function (array $pieceInfo) {
                    $details = $pieceInfo['PieceDetails'];
                    return Parcel::make(
                        (float) ($details['Width'] ?? 0.00),
                        (float) ($details['Height'] ?? 0.00),
                        (float) ($details['Depth'] ?? 0.00),
                        (float) ($details['Weight'] ?? 0.00),
                        ($details['WeightUnit'] ?? 'K') === 'K' ? Unit::CENTIMETER : Unit::INCH,
                        ($details['WeightUnit'] ?? 'K') === 'K' ? Unit::KILOGRAM : Unit::POUND
                    );
                }, $pieceInfos);

                $tracking = new Tracking('DHL', (string) $info[0]->GlobalProductCode, $activities);
                $tracking->estimatedDeliveryDate = $estimatedDelivery;
                $tracking->parcels = $parcels;

                return new TrackingResult(TrackingResult::STATUS_SUCCESS, $trackingNo, $body, $tracking);
            }, $info);
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
                'BL', 'HI', 'HO',
                //'AD', //"Scheduled for delivery as agreed"
                'SP', 'IA', 'SI', 'ST', 'NA',
                'CI', 'CU', 'LX', 'DI', 'SF', 'LV', 'UV', 'HN', 'DP',
                'PY', 'PM', 'BA',
                'CD', //Clearance delay
                'UD', //Uncontrollable Clearance Delay
                'HX', 'TD', 'CA',
                'NH', //recipient not home
                'MX', 'SS',
                'CS', //Please contact DHL
                'CM', 'RD', 'MS', 'MC',
                'OH', //shipment on hold
                'SC', 'WX',

                // returned to shipper
                'RT',
                //'RR', - Used to have RR as exception but that might just be "Customs status updated"
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
     * @return array
     */
    private function getMetaData(): array
    {
        return [
            'SoftwareName' => mb_substr(sprintf('%s/PHP %s', PHP_OS, PHP_VERSION), 0, 30, 'utf-8'),
            'SoftwareVersion' => mb_substr((string) PHP_VERSION, 0, 10, 'utf-8'),
        ];
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $now = date('c');
        $parcels = array_map(function (Parcel $parcel) use ($request): Parcel {
            return $request->units == ShipmentRequest::UNITS_IMPERIAL ?
                $parcel->convertTo(Unit::INCH, Unit::POUND) :
                $parcel->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);
        }, $request->parcels);

        $parcelsData = array_map(function (Parcel $parcel, int $idx): array {
            return [
                'PieceID' => $idx + 1,
                'PackageType' => 'YP',
                'Weight' => $parcel->weight->format(2),
                'Width' => $parcel->width->format(0),
                'Height' => $parcel->height->format(0),
                'Depth' => $parcel->length->format(0),
            ];
        }, $parcels, array_keys($parcels));

        $specialServices = $request->specialServices;

        // TODO: the signature service may or may not be broken
        // on the test endpoint. currently requests with signature
        // required enabled fails with the following error:
        //
        // <Condition>
        //    <ConditionCode>154</ConditionCode>
        //    <ConditionData>null field value is invalid</ConditionData>
        // </Condition>
        //
        // we're not sending any null values so it's difficult
        // to debug this.
        if ($request->signatureRequired && !in_array('SA', $request->specialServices)) {
            $specialServices[] = 'SA';
        }

        if ($request->insuredValue > 0) {
            $specialServices[] = 'II';
        }

        $lengthUnitName = $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'I' : 'C';
        $weightUnitName = $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'L' : 'K';

        $countryNames = require __DIR__ . '/../../countries.php';

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now,
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
                'MetaData' => $this->getMetaData(),
            ],
            'LanguageCode' => 'en',
            'PiecesEnabled' => 'Y',
            'Billing' => [
                'ShipperAccountNumber' => $this->credentials->getAccountNumber(),
                'ShippingPaymentType' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_RECIPIENT
                    ? 'R'
                    : 'S',
                'BillingAccountNumber' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_SENDER
                    ? $this->credentials->getAccountNumber()
                    : null,
                'DutyPaymentType' => $request->dutyPaymentType === ShipmentRequest::PAYMENT_TYPE_RECIPIENT
                    ? 'R'
                    : 'S',
                'DutyAccountNumber' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_SENDER
                    ? $this->credentials->getAccountNumber()
                    : null,
            ],
            'Consignee' => [
                'CompanyName' => $request->recipient->name,
                'AddressLine' => array_filter($request->recipient->lines),
                'City' => $request->recipient->city,
                'PostalCode' => $request->recipient->zip,
                'CountryCode' => $request->recipient->countryCode,
                'CountryName' => $countryNames[$request->recipient->countryCode],
                'Contact' => [
                    'PersonName' => $request->recipient->contactName,
                    'PhoneNumber' => $request->recipient->contactPhone,
                ],
            ],
            'Dutiable' => [
                'DeclaredValue' => number_format($request->value, 2, '.', ''),
                'DeclaredCurrency' => $request->currency,
            ],
            'ExportDeclaration' => [
                'InvoiceNumber' => $request->reference,
                'InvoiceDate' => $request->date->format('Y-m-d'),
                'ExportLineItem' => array_map(function (int $key, ExportDeclaration $decl) use ($request, $weightUnitName): array {
                    $weight = [
                        'Weight' => $decl->weight
                            ->convertTo($request->units == ShipmentRequest::UNITS_IMPERIAL ? Unit::POUND : Unit::KILOGRAM)
                            ->format(2),
                        'WeightUnit' => $weightUnitName,
                    ];
                    return [
                        'LineNumber' => $key + 1,
                        'Quantity' => $decl->quantity,
                        'QuantityUnit' => 'PCS',
                        'Description' => $decl->description,
                        'Value' => number_format($decl->value, 2, '.', ''),
                        'Weight' => $weight,
                        'GrossWeight' => $weight,
                        'ManufactureCountryCode' => $decl->originCountryCode,
                    ];
                }, array_keys($request->exportDeclarations), $request->exportDeclarations),
            ],
            'Reference' => [
                'ReferenceID' => $request->reference,
            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($request->parcels),
                'Pieces' => [
                    'Piece' => $parcelsData,
                ],
                'Weight' => array_reduce($parcels, function (Amount $carry, Parcel $parcel) {
                    return new Amount(
                        $carry->getValue() + $parcel->weight->getValue(),
                        $parcel->weight->getUnit()
                    );
                }, new Amount(0, ''))->format(2),
                'WeightUnit' => $weightUnitName,
                'GlobalProductCode' => $request->service,
                'Date' => $request->date->format('Y-m-d'),
                'Contents' => $request->contents,
                //'DoorTo' => 'DD',
                'DimensionUnit' => $lengthUnitName,
                'InsuredAmount' => number_format($request->insuredValue, 2, '.', ''),
                'IsDutiable' => $request->isDutiable ? 'Y' : 'N',
                'CurrencyCode' => $request->currency,
            ],
            'Shipper' => [
                'ShipperID' => $this->credentials->getAccountNumber(),
                'CompanyName' => $request->sender->name,
                'AddressLine' => $request->sender->lines,
                'City' => $request->sender->city,
                'PostalCode' => $request->sender->zip,
                'CountryCode' => $request->sender->countryCode,
                'CountryName' => $countryNames[$request->sender->countryCode],
                'Contact' => [
                    'PersonName' => $request->sender->contactName,
                    'PhoneNumber' => $request->sender->contactPhone,
                ],
            ],
            'SpecialService' => array_map(function (string $service): array {
                return [
                    'SpecialServiceType' => $service,
                ];
            }, $specialServices),
            'LabelImageFormat' => $request->labelFormat ?: 'PDF',
            'Label' => [
                'LabelTemplate' => $request->labelSize ?: '8X4_A4_PDF',
            ],
        ];

        if ($request->isDutiable && $request->incoterm) {
            $data['Dutiable']['TermsOfTrade'] = $request->incoterm;
        }

        foreach ($request->extra as $key => $value) {
            Arrays::set($data, $key, $value);
        }

        $data = removeKeysWithValues($data, [], null);
        $shipmentRequest = Xml::fromArray($data);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="6.2">
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
            $body = (string)$response->getBody();

            $number = '';
            $awbData = '';
            $parser = new XMLCallbackParser([
                'AirwayBillNumber' => function (DOMNode $node) use (&$number) {
                    $number = $node->textContent;
                },
                'OutputImage' => function (DOMNode $node) use (&$awbData) {
                    $awbData = $node->textContent;
                },
            ]);
            $parser->parse($body);

            if (!$number) {
                $this->throwError($body);
            }

            $data = base64_decode($awbData);

            return [new Shipment($number, 'DHL', $data, $body)];
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
        $errors = $this->getErrors($body);
        throw new ServiceException($errors, $body);
    }

    /**
     * @param string $body
     * @return string[]
     */
    protected function getErrors(string $body): array
    {
        $xml = new DOMDocument('1.0', 'utf-8');
        $xml->loadXML($body, LIBXML_PARSEHUGE);

        $arrayed = Xml::toArray($xml);
        $conditions = Arrays::get($arrayed, 'Response.Status.Condition');

        if (!$conditions) {
            return [];
        }

        return array_map(function (array $item) {
            $message = $item['ConditionData'];
            $message = preg_replace('/\s+/', ' ', $message);
            $message = $this->errorFormatter->format($message);
            return $message;
        }, Arrays::isNumericKeyArray($conditions) ? $conditions : [$conditions]);
    }

    protected function getErrorsAndMaybeThrow(string $body): void
    {
        $errors = $this->getErrors($body);

        if (!empty($errors)) {
            throw new ServiceException($errors, $body);
        }
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {
        return $this->getQuoteOrCapability($request, 'GetCapability')->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            $this->getErrorsAndMaybeThrow($body);

            $xml = new DOMDocument('1.0', 'utf-8');
            $xml->loadXML($body, LIBXML_PARSEHUGE);

            $arrayed = Xml::toArray($xml);
            $services = Arrays::get($arrayed, 'GetCapabilityResponse.Srvs.Srv') ?? [];

            if (!Arrays::isNumericKeyArray($services)) {
                $services = [$services];
            }

            return (new Collection($services))->map(function (array $service): string {
                return $service['GlobalProductCode'];
            })->value();
        });
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     * @throws \Exception
     */
    public function getProofOfDelivery(string $trackingNumber): PromiseInterface
    {
        throw new \Exception('Not implemented');
    }

    public function createPickup(PickupRequest $request): PromiseInterface
    {
        $now = new \DateTimeImmutable('now');

        /* @var Amount $totalWeight */
        $totalWeight = array_reduce($request->parcels, function (Amount $carry, Parcel $current) use ($request): Amount {
            $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
                $current->convertTo(Unit::INCH, Unit::POUND) :
                $current->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return new Amount($carry->getValue() + $parcel->weight->getValue(), $parcel->weight->getUnit());
        }, new Amount(0, ''));

        $parcels = array_map(function (Parcel $parcel) use ($request): Parcel {
            return $request->units == ShipmentRequest::UNITS_IMPERIAL ?
                $parcel->convertTo(Unit::INCH, Unit::POUND) :
                $parcel->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);
        }, $request->parcels);

        $parcelsData = array_map(function (Parcel $parcel, int $idx): array {
            return [
                'Weight' => $parcel->weight->format(2),
                'Width' => $parcel->width->format(0),
                'Height' => $parcel->height->format(0),
                'Depth' => $parcel->length->format(0),
            ];
        }, $parcels, array_keys($parcels));

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now->format('c'),
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
                'MetaData' => $this->getMetaData(),
            ],
            'RegionCode' => 'AM',
            'Requestor' => [
                'AccountType' => 'D',
                'AccountNumber' => $this->credentials->getAccountNumber(),
                'RequestorContact' => [
                    'PersonName' => $request->requestorAddress->contactName,
                    'Phone' => $request->requestorAddress->contactPhone,
                ],
                'CompanyName' => $request->requestorAddress->name,
                'Address1' => $request->requestorAddress->lines[0] ?? '',
                'Address2' => $request->requestorAddress->lines[1] ?? '',
                'Address3' => $request->requestorAddress->lines[2] ?? '',
                'City' => $request->requestorAddress->city,
                'CountryCode' => $request->requestorAddress->countryCode,
                'PostalCode' => $request->requestorAddress->zip,
            ],
            'Place' => [
                'LocationType' => $this->formatLocationType($request->locationType), // B - Business, R - Residence, C- (Business/Residence)
                'CompanyName' => $request->pickupAddress->name,
                'Address1' => $request->pickupAddress->lines[0] ?? '',
                'Address2' => $request->pickupAddress->lines[1] ?? '',
                'Address3' => $request->pickupAddress->lines[2] ?? '',
                'PackageLocation' => '',
                'City' => $request->pickupAddress->city,
                'StateCode' => $request->pickupAddress->state,
                'CountryCode' => $request->pickupAddress->countryCode,
                'PostalCode' => $request->pickupAddress->zip,
            ],
            'Pickup' => [
                'PickupDate' => $request->earliestPickup->format('Y-m-d'),
                // S - Same day pickup, A - Advanced pickup
                'PickupTypeCode' => $request->earliestPickup->format('Y-m-d') === $now->format('Y-m-d') ?
                    'S' :
                    'A',
                'ReadyByTime' => $request->earliestPickup->format('H:i'),
                'CloseTime' => $request->latestPickup->format('H:i'),
                'Pieces' => count($request->parcels),
                'weight' => [
                    'Weight' => $totalWeight->format(2),
                    'WeightUnit' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'L' : 'K',
                ],
            ],
            'PickupContact' => [
                'PersonName' => $request->pickupAddress->contactName,
                'Phone' => $request->pickupAddress->contactPhone,
            ],
            'ShipmentDetails' => [
                'AccountType' => 'D',
                'AccountNumber' => $this->credentials->getAccountNumber(),
                'NumberOfPieces' => count($request->parcels),
                'Weight' => $totalWeight->format(2),
                'WeightUnit' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'L' : 'K',
                /*
                    D : US Overnight  (>0.5 lb) and Worldwide Express Non-dutiable  (>0.5 lb)
                    X : USA Express Envelope   (less than or  = 0.5 lb) and Worldwide Express-International Express Envelope  (less than or = 0.5 lb)
                    W : Worldwide Express-Dutiable
                    Y : DHL Second Day Express . Must be Express Envelop with weight lessthan or = 0.5 lb
                    G : DHL Second Day . Weight > 0.5 lb or not an express envelop
                    T : DHL Ground Shipments',
                */
                'GlobalProductCode' => $request->service,
                'DoorTo' => $this->formatDeliveryServiceType($request->deliveryServiceType),
                'DimensionUnit' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'I' : 'C',
                'InsuredAmount' => number_format($request->insuredValue, 2, '.', ''),
                'InsuredCurrencyCode' => $request->currency,
                'Pieces' => [
                    'Piece' => $parcelsData
                ],
            ]
        ];
        $data = removeKeysWithValues($data, [], null);
        $shipmentRequest = Xml::fromArray($data);
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:BookPURequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com pickup-global-req.xsd" schemaVersion="3.0">
{$shipmentRequest}
</req:BookPURequest>
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
        ])->then(function (ResponseInterface $response) use ($request) {
            $body = (string)$response->getBody();

            $number = '';
            $locationCode = '';
            $parser = new XMLCallbackParser([
                'ConfirmationNumber' => function (DOMNode $node) use (&$number) {
                    $number = $node->textContent;
                },
                'OriginSvcArea' => function (DOMNode $node) use (&$locationCode) {
                    $locationCode = $node->textContent;
                },
            ]);
            $parser->parse($body);

            if (!$number || !$locationCode) {
                $this->throwError($body);
            }

            return new Pickup(
                'DHL',
                $number,
                $request->service,
                $request->earliestPickup,
                $locationCode,
                $body
            );
        });
    }

    /**
     * @param string $locationType
     * @return string
     */
    private function formatLocationType(string $locationType): string
    {
        switch ($locationType) {
            case PickupRequest::LOCATION_TYPE_BUSINESS:
                return 'B';
            case PickupRequest::LOCATION_TYPE_RESIDENTIAL:
                return 'R';
            case PickupRequest::LOCATION_TYPE_BUSINESS_RESIDENTIAL:
                return 'C';
            default:
                throw new \InvalidArgumentException('Invalid pickup location type');
        }
    }

    /**
     * @param string $deliveryService
     * @return string
     */
    private function formatDeliveryServiceType(string $deliveryService): string
    {
        switch ($deliveryService) {
            case PickupRequest::DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR:
                return 'DD';
            case PickupRequest::DELIVERY_SERVICE_TYPE_DOOR_TO_AIRPORT:
                return 'DA';
            case PickupRequest::DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR_NON_COMPLIANT:
                return 'DC';
            default:
                throw new \InvalidArgumentException('Invalid pickup delivery service type');
        }
    }

    public function cancelPickup(CancelPickupRequest $request): PromiseInterface
    {
        $now = new \DateTimeImmutable('now');

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now->format('c'),
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->getSiteID(),
                    'Password' => $this->credentials->getPassword(),
                ],
                'MetaData' => $this->getMetaData(),
            ],
            'RegionCode' => 'AM',
            'ConfirmationNumber' => $request->id,
            'RequestorName' => $request->requestorAddress->contactName,
            'CountryCode' => $request->requestorAddress->countryCode,
            'OriginSvcArea' => $request->locationCode,
            'PickupDate' => $request->date->format('Y-m-d'),
            'CancelTime' => $now->format('H:i'),
        ];
        $data = removeKeysWithValues($data, [], null);
        $shipmentRequest = Xml::fromArray($data);
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:CancelPURequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com pickup-global-req.xsd" schemaVersion="3.0">
{$shipmentRequest}
</req:CancelPURequest>
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
            $number = '';

            $parser = new XMLCallbackParser([
                'ConfirmationNumber' => function (DOMNode $node) use (&$number) {
                    $number = $node->textContent;
                },
            ]);
            $parser->parse($body);

            if (!$number) {
                $this->throwError($body);
            }

            return true;
        });
    }
}
