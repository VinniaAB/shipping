<?php
declare(strict_types=1);

namespace Vinnia\Shipping\DHL;

use Exception;
use InvalidArgumentException;
use DOMDocument;
use DOMNode;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Promise\PromiseInterface;
use LogicException;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\Pickup;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\TrackingResult;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Centimeter;
use Vinnia\Util\Measurement\Inch;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Pound;
use Vinnia\Util\Text\Xml;
use Vinnia\Util\Text\XmlCallbackParser;

class Service extends ServiceLike implements ServiceInterface
{
    protected function getQuoteOrCapability(QuoteRequest $request, string $elementName): PromiseInterface
    {
        [$lengthUnit, $weightUnit] = $request->determineUnits();
        $parcels = array_map(function (Parcel $parcel, int $idx) use ($request, $lengthUnit, $weightUnit): array {
            $p = $parcel->convertTo($lengthUnit, $weightUnit);

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
                        'SiteID' => $this->credentials->siteID,
                        'Password' => $this->credentials->password,
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
                    'PaymentAccountNumber' => $this->credentials->accountNumber,
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
                'isUTF8Support' => 'true',
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
                    'SiteID' => $this->credentials->siteID,
                    'Password' => $this->credentials->password,
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
                'isUTF8Support' => 'true',
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $xml = new DOMDocument('1.0', 'utf-8');
            $xml->loadXML($body);

            $arrayed = Xml::toArray($xml);
            $awbInfo = $arrayed['AWBInfo'] ?? [];

            // previously we were using "ShipmentInfo[ShipmentEvent]" to determine if
            // the track was successful. it turns out some labels do not have shipment
            // events (especially when they're newly created). instead let's check if
            // the product code exists, which should hopefully be accurate.
            $infos = Arrays::isNumericKeyArray($awbInfo)
                ? $awbInfo
                : [$awbInfo];

            return array_map(function (array $element) use ($body): TrackingResult {
                $info = Arrays::get($element, 'ShipmentInfo');
                $productCode = Arrays::get($element, 'ShipmentInfo.GlobalProductCode');

                $trackingNo = (string) $element['AWBNumber'];

                if (!$productCode) {
                    return new TrackingResult(TrackingResult::STATUS_ERROR, $trackingNo, $body);
                }

                $estimatedDelivery = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $info['EstDlvyDateUTC'] ?? '', new DateTimeZone('UTC'));

                // createFromFormat returns false when parsing fails.
                // we don't want any booleans in our result.
                $estimatedDelivery = $estimatedDelivery ?: null;
                $events = $info['ShipmentEvent'] ?? [];
                $events = Arrays::isNumericKeyArray($events)
                    ? $events
                    : [$events];

                $activities = (new Collection($events))->map(function (array $element) {
                    $area = $element['ServiceArea'];
                    $event = $element['ServiceEvent'];
                    $tz = 'UTC';
                    $address = Address::empty();

                    // ServiceArea.Description is a string of format {CITY}-{COUNTRY_ISO3}
                    // There might be several elements in the city name. One example of this
                    // is "LONDON-HEATHROW-GBR". In this case Heathrow is irrelevant and we
                    // are only interested in "LONDON".
                    if (preg_match('/([^\-]+)-.*([A-Z]{3})$/', $area['Description'], $matches)) {
                        $address = Address::fromArray([
                            'city' => $matches[1],
                            'country_code' => static::$countriesIso3ToIso2[$matches[2]],
                        ]);
                        $tz = $this->timezoneDetector->findByCountryAndCity(
                            $address->countryCode,
                            $address->city
                        );
                    }

                    $dtString = ((string)$element['Date']) . ' ' . ((string)$element['Time']);
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dtString, new DateTimeZone($tz ?: 'UTC'));

                    // the description will sometimes include the location too.
                    $description = $event['Description'] ?? '';

                    $status = $this->getStatusFromEventCode($event['EventCode'] ?? '');

                    // Append signature to description
                    if (strpos($description, 'Signed for by') !== false) {
                        $description .= ': ' . ((string)$element['Signatory'] ?: 'Not provided');
                    }

                    return new TrackingActivity($status, $description, $dt, $address);
                })->value();

                $pieceInfos = Arrays::get($element, 'Pieces.PieceInfo');

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
                        ($details['WeightUnit'] ?? 'K') === 'K' ? Centimeter::unit() : Inch::unit(),
                        ($details['WeightUnit'] ?? 'K') === 'K' ? Kilogram::unit() : Pound::unit()
                    );
                }, $pieceInfos);

                $tracking = new Tracking('DHL', (string) $productCode, $activities);
                $tracking->estimatedDeliveryDate = $estimatedDelivery;
                $tracking->parcels = $parcels;

                return new TrackingResult(TrackingResult::STATUS_SUCCESS, $trackingNo, $body, $tracking);
            }, $infos);
        });
    }

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

    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        return (new ShipmentService($this->guzzle, $this->credentials, $this->baseUrl, $this->errorFormatter))
            ->createShipment($request);
    }

    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        return (new ShipmentService($this->guzzle, $this->credentials, $this->baseUrl, $this->errorFormatter))
            ->cancelShipment($id, $data);
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

    public function getProofOfDelivery(string $trackingNumber): PromiseInterface
    {
        throw new Exception('Not implemented');
    }

    public function createPickup(PickupRequest $request): PromiseInterface
    {
        $now = new DateTimeImmutable('now');
        [$lengthUnit, $weightUnit] = $request->determineUnits();

        $parcels = array_map(
            fn ($parcel) => $parcel->convertTo($lengthUnit, $weightUnit),
            $request->parcels
        );

        $totalWeight = Parcel::getTotalWeight($parcels, $weightUnit);

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
                    'SiteID' => $this->credentials->siteID,
                    'Password' => $this->credentials->password,
                ],
                'MetaData' => $this->getMetaData(),
            ],
            'RegionCode' => 'AM',
            'Requestor' => [
                'AccountType' => 'D',
                'AccountNumber' => $this->credentials->accountNumber,
                'RequestorContact' => [
                    'PersonName' => $request->requestorAddress->contactName,
                    'Phone' => $request->requestorAddress->contactPhone,
                ],
                'CompanyName' => $request->requestorAddress->name,
                'Address1' => $request->requestorAddress->address1 ?: '',
                'Address2' => $request->requestorAddress->address2 ?: '',
                'Address3' => $request->requestorAddress->address3 ?: '',
                'City' => $request->requestorAddress->city,
                'CountryCode' => $request->requestorAddress->countryCode,
                'PostalCode' => $request->requestorAddress->zip,
            ],
            'Place' => [
                'LocationType' => $this->formatLocationType($request->locationType), // B - Business, R - Residence, C- (Business/Residence)
                'CompanyName' => $request->pickupAddress->name,
                'Address1' => $request->pickupAddress->address1 ?: '',
                'Address2' => $request->pickupAddress->address2 ?: '',
                'Address3' => $request->pickupAddress->address3 ?: '',
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
                'AccountNumber' => $this->credentials->accountNumber,
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
                    'Piece' => $parcelsData,
                ],
            ],
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
                'isUTF8Support' => 'true',
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
            $parser = new XmlCallbackParser([
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
                throw new InvalidArgumentException('Invalid pickup location type');
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
                throw new InvalidArgumentException('Invalid pickup delivery service type');
        }
    }

    public function cancelPickup(CancelPickupRequest $request): PromiseInterface
    {
        $now = new DateTimeImmutable('now');

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now->format('c'),
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->siteID,
                    'Password' => $this->credentials->password,
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
                'isUTF8Support' => 'true',
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $number = '';

            $parser = new XmlCallbackParser([
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
