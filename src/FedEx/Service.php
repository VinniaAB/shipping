<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 19:24
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\FedEx;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Promise;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use DateTimeImmutable;
use SimpleXMLElement;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\Xml;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;
use Vinnia\Util\Validation\Validator;

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

    function __construct(ClientInterface $guzzle, Credentials $credentials, string $url = self::URL_PRODUCTION)
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->url = $url;
    }

    /**
     * @param Address $address
     * @return array
     */
    private function addressToArray(Address $address): array
    {
        return [
            'StreetLines' => $address->lines,
            'City' => $address->city,
            'StateOrProvinceCode' => $address->state,
            'PostalCode' => $address->zip,
            'CountryCode' => $address->countryCode,
        ];
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
        $package = $request->units == QuoteRequest::UNITS_IMPERIAL ?
            $request->package->convertTo(Unit::INCH, Unit::POUND) :
            $request->package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        $sender = $request->sender;
        $recipient = $request->recipient;

        $rateRequest = Xml::fromArray([
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
                        'PaymentType' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_SENDER ?
                            'SENDER' :
                            'RECIPIENT',
                        'Payor' => [
                            'ResponsibleParty' => [
                                'AccountNumber' => $this->credentials->getAccountNumber(),
                            ],
                        ],
                    ],
                    'RateRequestTypes' => 'NONE',
                    'PackageCount' => 1,
                    'RequestedPackageLineItems' => [
                        'SequenceNumber' => 1,
                        'GroupNumber' => 1,
                        'GroupPackageCount' => 1,
                        'Weight' => [
                            'Units' => $request->units == QuoteRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                            'Value' => $package->weight->format(2),
                        ],
                        'Dimensions' => [
                            'Length' => $package->length->format(0),
                            'Width' => $package->width->format(0),
                            'Height' => $package->height->format(0),
                            'Units' => $request->units == QuoteRequest::UNITS_IMPERIAL ? 'IN' : 'CM',
                        ],
                    ],
                ],
            ],
        ]);

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/rate/v22">
   <p:Body>{$rateRequest}</p:Body>
</p:Envelope>
EOD;

        return $this->send('/rate', $body, function (ResponseInterface $response) {
            $body = (string) $response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $details = $xml->xpath('/SOAP-ENV:Envelope/SOAP-ENV:Body/*[local-name()=\'RateReply\']/*[local-name()=\'RateReplyDetails\']');

            return array_map(function (SimpleXMLElement $element): Quote {
                $product = (string) $element->{'ServiceType'};

                $total = $element
                    ->{'RatedShipmentDetails'}
                    ->{'ShipmentRateDetail'}
                    ->{'TotalNetChargeWithDutiesAndTaxes'};

                $amountString = (string) $total->{'Amount'};
                $amount = (int) round(((float) $amountString) * pow(10, 2));

                return new Quote('FedEx', $product, new Money($amount, new Currency((string) $total->{'Currency'})));
            }, $details);
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
                    'Major' => 14,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'SelectionDetails' => [
                    'PackageIdentifier' => [
                        'Type' => 'TRACKING_NUMBER_OR_DOORTAG',
                        'Value' => $trackingNumber,
                    ],
                ],
                'ProcessingOptions' => 'INCLUDE_DETAILED_SCANS',
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
            $body = (string) $response->getBody();
            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $arrayed = Xml::toArray($xml->xpath('/SOAP-ENV:Envelope/SOAP-ENV:Body')[0]);

            $validator = new Validator([
                'TrackReply.CompletedTrackDetails.TrackDetails.Notification.Severity' => 'required|ne:ERROR',
                'TrackReply.CompletedTrackDetails.TrackDetails.Events' => 'array',
                'TrackReply.CompletedTrackDetails.TrackDetails.Service.Type' => 'required|string',
            ]);

            $bag = $validator->validate($arrayed);

            if (count($bag) !== 0) {
                return new RejectedPromise($body);
            }

            $service = (string) Arrays::get($arrayed, 'TrackReply.CompletedTrackDetails.TrackDetails.Service.Type');
            $events = Arrays::get($arrayed, 'TrackReply.CompletedTrackDetails.TrackDetails.Events');

            if (!Xml::isNumericKeyArray($events)) {
                $events = [$events];
            }

            $activities = (new Collection($events))->map(function (array $element) {
                $status = $this->getStatusFromEventType((string) $element['EventType']);
                $description = $element['EventDescription'];
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

            return new Tracking('FedEx', $service, $activities);
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
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $package = $request->units == ShipmentRequest::UNITS_IMPERIAL ?
            $request->package->convertTo(Unit::INCH, Unit::POUND) :
            $request->package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

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
                    'Major' => 21,
                    'Intermediate' => 0,
                    'Minor' => 0,
                ],
                'RequestedShipment' => [
                    'ShipTimestamp' => $request->date->format('c'),
                    'DropoffType' => 'REGULAR_PICKUP',
                    'ServiceType' => $request->service,
                    'PackagingType' => 'YOUR_PACKAGING',
                    'Shipper' => [
                        'Contact' => [
                            'CompanyName' => $request->sender->name,
                            'PhoneNumber' => $request->sender->contactPhone,
                        ],
                        'Address' => $this->addressToArray($request->sender),
                    ],
                    'Recipient' => [
                        'Contact' => [
                            'CompanyName' => $request->recipient->name,
                            'PhoneNumber' => $request->recipient->contactPhone,
                        ],
                        'Address' => $this->addressToArray($request->recipient),
                    ],
                    'ShippingChargesPayment' => [
                        'PaymentType' => $request->shipmentPaymentType === ShipmentRequest::PAYMENT_TYPE_SENDER ?
                            'SENDER' :
                            'RECIPIENT',
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
                            'PaymentType' => $request->dutyPaymentType === ShipmentRequest::PAYMENT_TYPE_SENDER ?
                                'SENDER' :
                                'RECIPIENT',
                        ],
                        'CustomsValue' => [
                            'Currency' => $request->currency,
                            'Amount' => number_format($request->value, 2, '.', ''),
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
                                        ->format(2)
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
                        'ImageType' => 'PDF',
                        'LabelStockType' => 'PAPER_LETTER',
                    ],
                    'ShippingDocumentSpecification' => [],
                    'PackageCount' => 1,
                    'RequestedPackageLineItems' => [
                        'SequenceNumber' => 1,
                        'GroupNumber' => 1,
                        'GroupPackageCount' => 1,
                        'Weight' => [
                            'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'LB' : 'KG',
                            'Value' => $package->weight->format(2),
                        ],
                        'Dimensions' => [
                            'Length' => $package->length->format(0),
                            'Width' => $package->width->format(0),
                            'Height' => $package->height->format(0),
                            'Units' => $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'IN' : 'CM',
                        ],
                        'CustomerReferences' => [
                            'CustomerReferenceType' => 'CUSTOMER_REFERENCE',
                            'Value' => $request->reference,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($request->extra as $key => $value) {
            Arrays::set($data, $key, $value);
        }

        $data = Xml::removeKeysWithEmptyValues($data);
        $shipRequest = Xml::fromArray($data);

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/ship/v21">
   <p:Body>$shipRequest</p:Body>
</p:Envelope>
EOD;

        return $this->send('/ship', $body, function (ResponseInterface $response) {
            $body = (string) $response->getBody();

            // remove namespace prefixes to ease parsing
            $body = str_replace('SOAP-ENV:', '', $body);

            if (strpos($body, '<HighestSeverity>SUCCESS</HighestSeverity>') === false) {
                $this->throwError($body);
            }

            preg_match('/<TrackingNumber>(.+)<\/TrackingNumber>/', $body, $matches);

            $trackingNumber = $matches[1];

            preg_match('/<Label>.*<Image>(.+)<\/Image>.*<\/Label>/', $body, $matches);

            $image = base64_decode($matches[1]);

            return new Shipment($trackingNumber, 'FedEx', $image, $body);
        });
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
            $body = (string) $response->getBody();

            return $body;
        });
    }

    protected function throwError(string $body)
    {
        $xml = new SimpleXMLElement($body);
        $arrayed = Xml::toArray($xml);
        $notifications = Arrays::get($arrayed, 'Body.ProcessShipmentReply.Notifications');

        // when we convert XML-formatted data to an
        // array we can't really be sure which elements
        // may have multiple occurrences. in this case
        // we know that there may be multiple notifications.
        if (!Xml::isNumericKeyArray($notifications)) {
            $notifications = [$notifications];
        }

        $errors = array_map(function (array $notification): string {
            return $notification['Message'];
        }, $notifications);

        throw new ServiceException($errors, $body);
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
            ]
        ];

        $xml = Xml::fromArray($data);
        $body = <<<EOD
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/vacs/v8">
   <soapenv:Body>$xml</soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->send('/vacs', $body, function (ResponseInterface $response) {
            $body = (string) $response->getBody();
            $body = str_replace('SOAP-ENV:', '', $body);
            $xml = new SimpleXMLElement($body);
            $arrayed = Xml::toArray($xml);
            $services = Arrays::get($arrayed, 'Body.ServiceAvailabilityReply.Options') ?? [];

            if (!Xml::isNumericKeyArray($services)) {
                $services = [$services];
            }

            return (new Collection($services))->map(function (array $service): string {
                return $service['Service'];
            })->value();
        });
    }

}
