<?php
declare(strict_types=1);

namespace Vinnia\Shipping\FedEx;

use Closure;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Promise\PromiseInterface;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\Pickup;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\ProofOfDeliveryResult;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ErrorFormatterInterface;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use SimpleXMLElement;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\ExactErrorFormatter;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\TrackingResult;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;
use Vinnia\Util\Validation\Validator;
use Vinnia\Util\Text\Xml;
use DOMDocument;

class Service implements ServiceInterface
{
    const URL_TEST = 'https://wsbeta.fedex.com:443/web-services';
    const URL_PRODUCTION = 'https://ws.fedex.com:443/web-services';

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
    private $url;

    /**
     * @var null|ErrorFormatterInterface
     */
    private $errorFormatter;

    /**
     * @var array
     */
    private $residentailServices = [
        'GROUND_HOME_DELIVERY',
    ];

    /**
     * Service constructor.
     * @param ClientInterface $guzzle
     * @param Credentials $credentials
     * @param string $url
     * @param null|ErrorFormatterInterface $errorFormatter
     */
    public function __construct(
        ClientInterface $guzzle,
        Credentials $credentials,
        string $url = self::URL_PRODUCTION,
        ?ErrorFormatterInterface $errorFormatter = null

    ) {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->url = $url;
        $this->errorFormatter = $errorFormatter === null ?
            new ExactErrorFormatter() :
            $errorFormatter;
    }

    /**
     * @param Address $address
     * @param bool $isResidential
     * @return array
     */
    private function addressToArray(Address $address, bool $isResidential = false): array
    {
        // fedex only supports 2 street lines so
        // let's put everything that overflows
        // into the 2nd line.
        $lines = [
            $address->lines[0] ?? '',
            implode(', ', array_slice($address->lines, 1)),
        ];

        $addressArray = [
            'StreetLines' => array_filter($lines),
            'City' => $address->city,
            'StateOrProvinceCode' => $address->state,
            'PostalCode' => $address->zip,
            'CountryCode' => $address->countryCode,
        ];

        if ($isResidential) {
            $addressArray['Residential'] = true;
        }

        return $addressArray;
    }

    /**
     * @param string $endpoint
     * @param string $body
     * @param Closure $success
     * @param Closure|null $error
     * @return PromiseInterface
     */
    private function send(string $endpoint, string $body, Closure $success, Closure $error = null): PromiseInterface
    {
        return $this->guzzle->requestAsync('POST', $this->url . $endpoint, [
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then($success, $error);
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface
    {
        $parcels = array_map(function (Parcel $parcel, int $idx) use ($request): array {
            $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
                $parcel->convertTo(Unit::INCH, Unit::POUND) :
                $parcel->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return [
                'SequenceNumber' => $idx + 1,
                'GroupNumber' => 1,
                'GroupPackageCount' => 1,
                'Weight' => [
                    'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                    'Value' => $parcel->weight->format(2),
                ],
                'Dimensions' => [
                    'Length' => $parcel->length->format(0),
                    'Width' => $parcel->width->format(0),
                    'Height' => $parcel->height->format(0),
                    'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'IN' : 'CM',
                ],
            ];
        }, $request->parcels, array_keys($request->parcels));

        $sender = $request->sender;
        $recipient = $request->recipient;

        $rateRequest = [
            'RateRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'crs',
                    'Major' => 22,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'RequestedShipment' => [
                    'DropoffType' => 'REGULAR_PICKUP',
                    'PackagingType' => 'YOUR_PACKAGING',
                    'Shipper' => [
                        'Address' => $this->addressToArray($sender),
                    ],
                    'Recipient' => [
                        'Address' => $this->addressToArray($recipient),
                    ],
                    'ShippingChargesPayment' => [
                        'PaymentType' => 'SENDER',
                        'Payor' => [
                            'ResponsibleParty' => [
                                'AccountNumber' => $this->credentials->getAccountNumber(),
                            ],
                        ],
                    ],
                    'RateRequestTypes' => 'NONE',
                    'PackageCount' => count($request->parcels),
                    'RequestedPackageLineItems' => $parcels,
                ],
            ],
        ];

        $rateRequest = removeKeysWithValues($rateRequest, [], null);
        $xml = Xml::fromArray($rateRequest);

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/rate/v22">
   <p:Body>{$xml}</p:Body>
</p:Envelope>
EOD;

        return $this->send('/rate', $body, function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $details = $xml->xpath('/SOAP-ENV:Envelope/SOAP-ENV:Body/*[local-name()=\'RateReply\']/*[local-name()=\'RateReplyDetails\']');

            return array_map(function (SimpleXMLElement $element): Quote {
                $product = (string)$element->{'ServiceType'};

                $total = $element
                    ->{'RatedShipmentDetails'}
                    ->{'ShipmentRateDetail'}
                    ->{'TotalNetChargeWithDutiesAndTaxes'};

                $amountString = (string)$total->{'Amount'};
                $amount = (float) $amountString;

                return new Quote('FedEx', $product, Array ('amount'=> $amount, 'currency' => (string)$total->{'Currency'}));
            }, $details);
        });
    }

    /**
     * @inheritdoc
     */
    public function getTrackingStatus(array $trackingNumbers, array $options = []): PromiseInterface
    {
        if (count($trackingNumbers) > 30) {
            throw new LogicException("FedEx only allows tracking of 30 shipments at a time.");
        }

        $trackRequest = Xml::fromArray([
            'TrackRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'trck',
                    'Major' => 16,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'SelectionDetails' => array_map(function (string $num) {
                    return [
                        'PackageIdentifier' => [
                            'Type' => 'TRACKING_NUMBER_OR_DOORTAG',
                            'Value' => $num,
                        ],
                    ];
                }, $trackingNumbers),
                'ProcessingOptions' => 'INCLUDE_DETAILED_SCANS',
            ],
        ]);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/track/v16">
   <soapenv:Header />
   <soapenv:Body>{$trackRequest}</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/track', $body, function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $doc = new DOMDocument('1.0', 'utf-8');
            $doc->loadXML($body, LIBXML_PARSEHUGE);
            $arrayed = Xml::toArray($doc);
            $items = Arrays::get($arrayed['SOAP-ENV:Body'], 'TrackReply.CompletedTrackDetails');

            if (!Arrays::isNumericKeyArray($items)) {
                $items = [$items];
            }

            $validator = new Validator([
                'TrackDetails.Notification.Severity' => 'required|ne:ERROR',
                'TrackDetails.Events' => 'array',
                'TrackDetails.Service.Type' => 'required|string',
                'TrackingDetails.PackageDimensions' => 'array',
            ]);

            return array_map(function (array $item) use ($body, $validator) {
                $trackingNo = (string)Arrays::get($item, 'TrackDetails.TrackingNumber');
                $bag = $validator->validate($item);

                if (count($bag) !== 0) {
                    return new TrackingResult(TrackingResult::STATUS_ERROR, '', $body);
                }

                $service = (string)Arrays::get($item, 'TrackDetails.Service.Type');
                $datesOrTimes = Arrays::get($item, 'TrackDetails.DatesOrTimes') ?? [];
                $estimatedDelivery = null;
                foreach ($datesOrTimes as $shipmentDate) {
                    /** If shipment delivered, this is replaced by ACTUAL_DELIVERY */
                    $dateType = $shipmentDate['Type'] ?? '';
                    if ('ESTIMATED_DELIVERY' == $dateType) {
                        $estimatedDelivery = new DateTimeImmutable($shipmentDate['DateOrTimestamp']);
                        // is the missing break here intentional?
                    }
                }

                $events = Arrays::get($item, 'TrackDetails.Events') ?? [];

                if (!Arrays::isNumericKeyArray($events)) {
                    $events = [$events];
                }

                $activities = (new Collection($events))->map(function (array $element) {
                    $status = $this->getStatusFromEventType((string)$element['EventType']);
                    $description = $element['EventDescription'] ?? '';
                    $dt = new DateTimeImmutable($element['Timestamp']);
                    $address = new Address(
                        '',
                        [],
                        $element['Address']['PostalCode'] ?? '',
                        $element['Address']['City'] ?? '',
                        $element['Address']['StateOrProvinceCode'] ?? '',
                        $element['Address']['CountryName'] ?? ''
                    );

                    return new TrackingActivity($status, $description, $dt, $address);
                })->value();

                $dimensions = Arrays::get($item, 'TrackDetails.PackageDimensions', [
                    'Width' => 0.00,
                    'Height' => 0.00,
                    'Length' => 0.00,
                    'Units' => 'CM',
                ]);
                $weight = Arrays::get($item, 'TrackDetails.PackageWeight', [
                    'Value' => 0.00,
                    'Units' => 'KG',
                ]);

                $parcel = Parcel::make(
                    (float) $dimensions['Width'],
                    (float) $dimensions['Height'],
                    (float) $dimensions['Length'],
                    (float) $weight['Value'],
                    $dimensions['Units'] === 'IN' ? Unit::INCH : Unit::CENTIMETER,
                    $weight['Units'] === 'LB' ? Unit::POUND : Unit::KILOGRAM
                );
                $tracking = new Tracking('FedEx', $service, $activities);
                $tracking->estimatedDeliveryDate = $estimatedDelivery;
                $tracking->parcels = [$parcel];
                $trackingResult = new TrackingResult(TrackingResult::STATUS_SUCCESS, $trackingNo, $body, $tracking);

                return $trackingResult;
            }, $items);
        });
    }

    /**
     * @param string $type
     * @return int
     */
    private function getStatusFromEventType(string $type): int
    {
        $type = mb_strtoupper($type, 'utf-8');

        // status mappings stolen from keeptracker.
        $typeMap = [
            TrackingActivity::STATUS_DELIVERED => [
                'DL',
            ],
            TrackingActivity::STATUS_EXCEPTION => [
                // cancelled
                'CA',

                // general issues
                'CD', 'DY', 'DE', 'HL', 'CH', 'SE',

                // returned to shipper
                'RS',
            ],
            TrackingActivity::STATUS_NOTIFICATION => [
                'OC',
            ],
        ];

        foreach ($typeMap as $status => $types) {
            if (in_array($type, $types)) {
                return $status;
            }
        }

        return TrackingActivity::STATUS_IN_TRANSIT;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     * @throws Exception
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        /* @var Amount $totalWeight */
        $totalWeight = array_reduce($request->parcels, function (Amount $carry, Parcel $current) use ($request): Amount {
            $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
                $current->convertTo(Unit::INCH, Unit::POUND) :
                $current->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return new Amount($carry->getValue() + $parcel->weight->getValue(), $parcel->weight->getUnit());
        }, new Amount(0, ''));

        /* @var Shipment[] $shipments */
        $shipments = [];

        $masterTrackingId = null;

        // if this shipment contains multiple parcels we need
        // to send one request per parcel. if one request fails
        // we need to cancel the other shipments.
        foreach ($request->parcels as $idx => $parcel) {
            $body = $this->buildShipmentRequestBody($request, $idx, $totalWeight, $masterTrackingId);

            try {
                /* @var Shipment $shipment */
                $shipment = $this->send('/ship', $body, function (ResponseInterface $response) {
                    return $this->parseShipmentRequestResponse($response);
                })->wait();

                $masterTrackingId = $masterTrackingId ?? $shipment->id;

                $shipments[] = $shipment;
            } catch (Exception $e) {

                // if one parcel fails we need to rollback the other shipments
                foreach ($shipments as $shipment) {
                    $this->cancelShipment($shipment->id, [
                        'type' => 'FEDEX',
                    ])->wait();
                }

                throw $e;
            }
        }

        return promise_for($shipments);
    }

    protected function buildShipmentRequestBody(
        ShipmentRequest $request,
        int $parcelIndex,
        Amount $totalWeight,
        ?string $masterTrackingId = null
    ): string {
        $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
            $request->parcels[$parcelIndex]->convertTo(Unit::INCH, Unit::POUND) :
            $request->parcels[$parcelIndex]->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        $data = [
            'ProcessShipmentRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'ship',
                    'Major' => 25,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'RequestedShipment' => [
                    'ShipTimestamp' => $request->date->format('c'),
                    'DropoffType' => 'REGULAR_PICKUP',
                    'ServiceType' => $request->service,
                    'PackagingType' => 'YOUR_PACKAGING',
                    'TotalWeight' => $parcelIndex === 0 ? [
                        'Units' => $totalWeight->getUnit() === Unit::POUND ? 'LB' : 'KG',
                        'Value' => $totalWeight->format(2),
                    ] : null,
                    'TotalInsuredValue' => [
                        'Currency' => $request->currency,
                        'Amount' => number_format($request->insuredValue, 2, '.', ''),
                    ],
                    'Shipper' => [
                        'Contact' => [
                            'PersonName' => $request->sender->contactName,
                            'CompanyName' => $request->sender->name,
                            'PhoneNumber' => $request->sender->contactPhone,
                        ],
                        'Address' => $this->addressToArray($request->sender),
                    ],
                    'Recipient' => [
                        'Contact' => [
                            'PersonName' => $request->recipient->contactName,
                            'CompanyName' => $request->recipient->name,
                            'PhoneNumber' => $request->recipient->contactPhone,
                        ],
                        'Address' => $this->addressToArray(
                            $request->recipient,
                            $this->serviceIsResidential($request->service)
                        ),
                    ],
                    'ShippingChargesPayment' => [
                        'PaymentType' => 'SENDER',
                        'Payor' => [
                            'ResponsibleParty' => [
                                'AccountNumber' => $this->credentials->getAccountNumber(),
                            ],
                        ],
                    ],
                    'SpecialServicesRequested' => [
                        'SpecialServiceTypes' => $request->specialServices,
                    ],
                    'CustomsClearanceDetail' => [
                        'DutiesPayment' => [
                            'PaymentType' => $request->getPaymentTypeOfIncoterm() === ShipmentRequest::PAYMENT_TYPE_SENDER ?
                                'SENDER' :
                                'RECIPIENT',
                            'Payor' => [
                                'ResponsibleParty' => [
                                    'AccountNumber' => $this->credentials->getAccountNumber(),
                                ],
                            ],
                        ],
                        'CustomsValue' => [
                            'Currency' => $request->currency,
                            'Amount' => number_format($request->value, 2, '.', ''),
                        ],
                        'CommercialInvoice' => [
                            'TermsOfSale' => $request->incoterm,
                        ],
                        'Commodities' => array_map(function (ExportDeclaration $decl) use ($request) {
                            return [
                                'NumberOfPieces' => $decl->quantity,
                                'Description' => $decl->description,
                                'CountryOfManufacture' => $decl->originCountryCode,
                                'Weight' => [
                                    'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                                    'Value' => $decl->weight
                                        ->convertTo($request->units == ShipmentRequest::UNITS_IMPERIAL ? Unit::POUND : Unit::KILOGRAM)
                                        ->format(2),
                                ],
                                'Quantity' => $decl->quantity,
                                'QuantityUnits' => 'Pieces',
                                'UnitPrice' => [
                                    'Currency' => $decl->currency,
                                    'Amount' => number_format($decl->value / $decl->quantity, 2, '.', ''),
                                ],
                            ];
                        }, $request->exportDeclarations),
                    ],
                    'LabelSpecification' => [
                        'LabelFormatType' => 'COMMON2D',
                        'ImageType' => $request->labelFormat ?: 'PDF',
                        'LabelStockType' => $request->labelSize ?: 'PAPER_LETTER',
                    ],
                    'ShippingDocumentSpecification' => [],
                    'MasterTrackingId' => $parcelIndex === 0 ? null : [
                        'TrackingNumber' => $masterTrackingId,
                    ],
                    'PackageCount' => count($request->parcels),
                    'RequestedPackageLineItems' => [
                        [
                            'SequenceNumber' => $parcelIndex + 1,
                            'GroupNumber' => 1,
                            'GroupPackageCount' => 1,
                            'Weight' => [
                                'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                                'Value' => $parcel->weight->format(2),
                            ],
                            'Dimensions' => [
                                'Length' => $parcel->length->format(0),
                                'Width' => $parcel->width->format(0),
                                'Height' => $parcel->height->format(0),
                                'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'IN' : 'CM',
                            ],
                            'CustomerReferences' => [
                                'CustomerReferenceType' => 'CUSTOMER_REFERENCE',
                                'Value' => $request->reference,
                            ],
                            'SpecialServicesRequested' => [
                                'SpecialServiceTypes' => [
                                    $request->signatureRequired ? 'SIGNATURE_OPTION' : null,
                                ],
                                'SignatureOptionDetail' => $request->signatureRequired ? [
                                    'OptionType' => 'DIRECT',
                                ] : null,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($request->extra as $key => $value) {
            Arrays::set($data, $key, $value);
        }

        $data = removeKeysWithValues($data, [], null);
        $shipRequest = Xml::fromArray($data);

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/ship/v25">
   <p:Body>$shipRequest</p:Body>
</p:Envelope>
EOD;

        return $body;
    }

    /**
     * @param ResponseInterface $response
     * @return Shipment
     * @throws ServiceException
     */
    protected function parseShipmentRequestResponse(ResponseInterface $response): Shipment
    {
        $body = (string)$response->getBody();

        // remove namespace prefixes to ease parsing
        $body = str_replace('SOAP-ENV:', '', $body);

        if ($this->isFailedAuthResponse($body)) {
            $this->throwAuthError($body);
        }

        if ($this->isErrorResponse($body)) {
            $this->throwError($body, 'Body.ProcessShipmentReply.Notifications');
        }

        preg_match('/<TrackingIds>.*<TrackingNumber>([^<]+)</', $body, $matches);

        $trackingNumber = $matches[1];

        preg_match('/<Label>.*<Image>([^<]+)</', $body, $matches);

        $image = base64_decode($matches[1]);

        return new Shipment($trackingNumber, 'FedEx', $image, $body);
    }

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        $deleteRequest = Xml::fromArray([
            'DeleteShipmentRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'ship',
                    'Major' => 21,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'TrackingId' => [
                    'TrackingIdType' => $data['type'],
                    'TrackingNumber' => $id,
                ],
                'DeletionControl' => 'DELETE_ALL_PACKAGES',
            ],
        ]);

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/ship/v21">
   <p:Body>$deleteRequest</p:Body>
</p:Envelope>
EOD;

        return $this->send('/ship', $body, function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            return $body;
        });
    }

    protected function throwError(string $body, string $notificationKey)
    {
        $xml = new SimpleXMLElement($body);
        $arrayed = Xml::toArray($xml);
        $notifications = Arrays::get($arrayed, $notificationKey);

        // when we convert XML-formatted data to an
        // array we can't really be sure which elements
        // may have multiple occurrences. in this case
        // we know that there may be multiple notifications.
        if (!Arrays::isNumericKeyArray($notifications)) {
            $notifications = [$notifications];
        }

        $errors = array_map(function (array $notification): string {
            return $this->errorFormatter->format($notification['Message']);
        }, $notifications);

        throw new ServiceException($errors, $body);
    }

    /**
     * @param string $body
     * @throws ServiceException
     */
    protected function throwAuthError(string $body)
    {
        throw new ServiceException([$this->errorFormatter->format('Authentification failed')], $body);
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {
        $data = [
            'ServiceAvailabilityRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'vacs',
                    'Major' => 8,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'Origin' => [
                    'PostalCode' => $request->sender->zip,
                    'CountryCode' => $request->sender->countryCode,
                ],
                'Destination' => [
                    'PostalCode' => $request->recipient->zip,
                    'CountryCode' => $request->recipient->countryCode,
                ],
                'ShipDate' => $request->date->format('Y-m-d'),
            ],
        ];

        $xml = Xml::fromArray($data);
        $body = <<<EOD
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/vacs/v8">
   <soapenv:Body>$xml</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/vacs', $body, function (ResponseInterface $response) {
            $body = (string)$response->getBody();
            $body = str_replace('SOAP-ENV:', '', $body);
            $xml = new SimpleXMLElement($body);
            $arrayed = Xml::toArray($xml);
            $services = Arrays::get($arrayed, 'Body.ServiceAvailabilityReply.Options') ?? [];

            if (!Arrays::isNumericKeyArray($services)) {
                $services = [$services];
            }

            return (new Collection($services))
                ->filter(fn ($service) => ($service['Service'] ?? '') !== '')
                ->map(fn ($service) => $service['Service'])
                ->value();
        });
    }

    /**
     * @param string $body
     * @return bool
     */
    protected function isErrorResponse(string $body): bool
    {
        return preg_match('/<HighestSeverity>(FAILURE|ERROR)<\/HighestSeverity>/', $body) === 1;
    }

    /**
     * @param string $body
     * @return bool
     */
    protected function isFailedAuthResponse(string $body): bool
    {
        return strpos($body, 'Authentication Failed') !== false;
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     *
     */
    public function getProofOfDelivery(string $trackingNumber): PromiseInterface
    {
        $trackRequest = Xml::fromArray([
            'GetTrackingDocumentsRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'trck',
                    'Major' => 14,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'SelectionDetails' => [
                    'PackageIdentifier' => [
                        'Type' => 'TRACKING_NUMBER_OR_DOORTAG',
                        'Value' => $trackingNumber,
                    ],
                    'SecureSpodAccount' => $this->credentials->getAccountNumber(),
                ],
                'TrackingDocumentSpecification' => [
                    'DocumentTypes' => 'SIGNATURE_PROOF_OF_DELIVERY',
                    'SignatureProofOfDeliveryDetail' => [
                        'DocumentFormat' => [
                            'Dispositions' => [
                                'DispositionType' => 'RETURN',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/track/v14">
   <soapenv:Header />
   <soapenv:Body>{$trackRequest}</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/track', $body, function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $arrayed = Xml::toArray($xml->xpath('/SOAP-ENV:Envelope/SOAP-ENV:Body')[0]);

            $validator = new Validator([
                'GetTrackingDocumentsReply.Notifications.Severity' => 'required|eq:SUCCESS',
                'GetTrackingDocumentsReply.Documents' => 'array',
                'GetTrackingDocumentsReply.Documents.Parts' => 'array',
                'GetTrackingDocumentsReply.Documents.Parts.Content' => 'required|string',
            ]);

            $bag = $validator->validate($arrayed);

            if (count($bag) !== 0) {
                return new ProofOfDeliveryResult(ProofOfDeliveryResult::STATUS_ERROR, $body);
            }

            $document = base64_decode($arrayed['GetTrackingDocumentsReply']['Documents']['Parts']['Content']);

            if (!$document) {
                return new ProofOfDeliveryResult(ProofOfDeliveryResult::STATUS_ERROR, $body);
            }

            return new ProofOfDeliveryResult(ProofOfDeliveryResult::STATUS_SUCCESS, $body, $document);
        });
    }

    /**
     * @param PickupRequest $request
     * @return PromiseInterface
     */
    public function createPickup(PickupRequest $request): PromiseInterface
    {
        /* @var Amount $totalWeight */
        $totalWeight = array_reduce($request->parcels, function (Amount $carry, Parcel $current) use ($request): Amount {
            $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
                $current->convertTo(Unit::INCH, Unit::POUND) :
                $current->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return new Amount($carry->getValue() + $parcel->weight->getValue(), $parcel->weight->getUnit());
        }, new Amount(0, ''));

        $trackRequest = Xml::fromArray([
            'CreatePickupRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'disp',
                    'Major' => 17,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'OriginDetail' => [
                    'PickupLocation' => [
                        'Contact' => [
                            'PersonName' => $request->pickupAddress->contactName,
                            'CompanyName' => $request->pickupAddress->name,
                            'PhoneNumber' => $request->pickupAddress->contactPhone,
                        ],
                        'Address' => $this->addressToArray(
                            $request->pickupAddress,
                            false
                        ),
                    ],
                    /**
                     * The time is local to the pickup postal code.
                     * Do not include a TZD (time zone designator) as it will be ignored.
                     */
                    'ReadyTimestamp' => $request->earliestPickup->format('c'),
                    'CompanyCloseTime' => $request->latestPickup->format('H:i:s'),
                ],
                'PackageCount' => count($request->parcels),
                'TotalWeight' => [
                    'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                    'Value' => $totalWeight->format(2),
                ],
                'CarrierCode' => $request->service,
                'Remarks' => $request->notes,
            ],
        ]);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/pickup/v17">
   <soapenv:Header />
   <soapenv:Body>{$trackRequest}</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/pickup', $body, function ($response) use ($request) {
            return $this->parsePickupRequestResponse(
                $response,
                $request->service,
                $request->earliestPickup
            );
        }, function (ServerException $exception) {
            throw $exception;
        });
    }

    /**
     * @param ResponseInterface $response
     * @return Pickup
     * @throws ServiceException
     */
    protected function parsePickupRequestResponse(
        ResponseInterface $response,
        string $service,
        DateTimeImmutable $date
    ): Pickup {
        $body = (string)$response->getBody();

        // remove namespace prefixes to ease parsing
        $body = str_replace('SOAP-ENV:', '', $body);

        if ($this->isFailedAuthResponse($body)) {
            $this->throwAuthError($body);
        }

        if ($this->isErrorResponse($body)) {
            $this->throwError($body, 'Body.CreatePickupReply.Notifications');
        }

        preg_match('/<PickupConfirmationNumber>(.*)<\/PickupConfirmationNumber>/', $body, $matches);

        $id = $matches[1];

        preg_match('/<Location>(.*)<\/Location>/', $body, $matches);

        $locationCode = $matches[1];

        return new Pickup('FedEx', $id, $service, $date, $locationCode, $body);
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     * @throws ServiceException
     */
    protected function parseCancelPickupRequestResponse(ResponseInterface $response): bool
    {
        $body = (string)$response->getBody();

        // remove namespace prefixes to ease parsing
        $body = str_replace('SOAP-ENV:', '', $body);

        if ($this->isFailedAuthResponse($body)) {
            $this->throwAuthError($body);
        }

        if ($this->isErrorResponse($body)) {
            $this->throwError($body, 'Body.CancelPickupReply.Notifications');
        }

        return true;
    }

    /**
     * @param string $id
     * @param string $location
     * @param string $service
     * @param DateTimeImmutable $date
     * @return PromiseInterface
     */
    public function cancelPickup(CancelPickupRequest $request): PromiseInterface
    {
        $trackRequest = Xml::fromArray([
            'CancelPickupRequest' => [
                'WebAuthenticationDetail' => [
                    'UserCredential' => [
                        'Key' => $this->credentials->getCredentialKey(),
                        'Password' => $this->credentials->getCredentialPassword(),
                    ],
                ],
                'ClientDetail' => [
                    'AccountNumber' => $this->credentials->getAccountNumber(),
                    'MeterNumber' => $this->credentials->getMeterNumber(),
                ],
                'Version' => [
                    'ServiceId' => 'disp',
                    'Major' => 17,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'CarrierCode' => $request->service,
                'PickupConfirmationNumber' => $request->id,
                'ScheduledDate' => $request->date->format('Y-m-d'),
                'Location' => $request->locationCode,
            ],
        ]);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/pickup/v17">
   <soapenv:Header />
   <soapenv:Body>{$trackRequest}</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/pickup', $body, function ($response) {
            return $this->parseCancelPickupRequestResponse($response);
        }, function (ServerException $exception) {
            throw $exception;
        });
    }

    /**
     * @param string $service
     * @return bool
     */
    private function serviceIsResidential(string $service): bool
    {
        return in_array($service, $this->residentailServices);
    }
}
