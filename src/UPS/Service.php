<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:36
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\UPS;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Shipping\TrackingResult;
use Vinnia\Shipping\Xml;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://wwwcie.ups.com/rest';
    const URL_PRODUCTION = 'https://onlinetools.ups.com/rest';
    const NON_SI_COUNTRIES = ['US'];

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
     * Service constructor.
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
        $sender = $request->sender;
        $recipient = $request->recipient;

        // UPS doesn't allow us to use SI units inside some countries
        $nonSi = in_array(mb_strtoupper($sender->countryCode, 'utf-8'), self::NON_SI_COUNTRIES);
        $lengthUnit = $nonSi ? 'IN' : 'CM';
        $weightUnit = $nonSi ? 'LBS' : 'KGS';

        $parcels = array_map(function (Parcel $parcel) use ($nonSi): Parcel {
            return $parcel->convertTo(
                $nonSi ? Unit::INCH : Unit::CENTIMETER,
                $nonSi ? Unit::POUND : Unit::KILOGRAM
            );
        }, $request->parcels);

        $body = [
            'UPSSecurity' => [
                'UsernameToken' => [
                    'Username' => $this->credentials->getUsername(),
                    'Password' => $this->credentials->getPassword(),
                ],
                'ServiceAccessToken' => [
                    'AccessLicenseNumber' => $this->credentials->getAccessLicense(),
                ],
            ],
            'RateRequest' => [
                'Request' => [
                    'RequestOption' => 'Shop',
                    //'TransactionReference' => [
                    //    'CustomerContext' => '',
                    //],
                ],
                'Shipment' => [
                    'Shipper' => [
                        'Name' => '',
                        'ShipperNumber' => '',
                        'Address' => [
                            'AddressLine' => array_filter($sender->lines),
                            'City' => $sender->city,
                            'StateProvinceCode' => '',
                            'PostalCode' => $sender->zip,
                            'CountryCode' => $sender->countryCode,
                        ],
                    ],
                    'ShipTo' => [
                        'Name' => '',
                        'Address' => [
                            'AddressLine' => array_filter($recipient->lines),
                            'City' => $recipient->city,
                            'StateProvinceCode' => $recipient->state,
                            'PostalCode' => $recipient->zip,
                            'CountryCode' => $recipient->countryCode,
                        ],
                    ],
                    'ShipFrom' => [
                        'Name' => '',
                        'Address' => [
                            'AddressLine' => array_filter($sender->lines),
                            'City' => $sender->city,
                            'StateProvinceCode' => $sender->state,
                            'PostalCode' => $sender->zip,
                            'CountryCode' => $sender->countryCode,
                        ],
                    ],
                    'Package' => array_map(function (Parcel $parcel) use ($lengthUnit, $weightUnit): array {
                        return [
                            'PackagingType' => [
                                'Code' => '02',
                            ],
                            'Dimensions' => [
                                'UnitOfMeasurement' => [
                                    'Code' => $lengthUnit,
                                ],
                                'Length' => $parcel->length->format(2, '.', ''),
                                'Width' => $parcel->width->format(2, '.', ''),
                                'Height' => $parcel->height->format(2, '.', ''),
                            ],
                            'PackageWeight' => [
                                'UnitOfMeasurement' => [
                                    'Code' => $weightUnit,
                                ],
                                'Weight' => $parcel->weight->format(2, '.', ''),
                            ],
                        ];
                    }, $parcels),
                    //'ShipmentRatingOptions' => [
                    //    'NegotiatedRatesIndicator' => '',
                    //],
                ],
            ],
        ];

        return $this->guzzle->requestAsync('POST', $this->baseUrl . '/Rate', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = json_decode((string) $response->getBody(), true);

            if (Arrays::get($body, 'RateResponse.RatedShipment') === null) {
                return new RejectedPromise($body);
            }

            $shipments = $body['RateResponse']['RatedShipment'];

            // sometimes UPS likes to return a single rate response.
            // this causes the json to appear as an object instead
            // of an array.
            $shipments = Xml::isNumericKeyArray($shipments) ? $shipments : [$shipments];

            return array_map(function (array $shipment): Quote {
                $charges = $shipment['TotalCharges'];
                $amount = (int) round(((float) $charges['MonetaryValue']) * pow(10, 2));

                return new Quote(
                    'UPS',
                    (string) Arrays::get($shipment, 'Service.Code'),
                    new Money($amount, new Currency($charges['CurrencyCode']))
                );
            }, $shipments);
        });
    }

    /**
     * @param string $trackingNumber
     * @param array $options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
    {
        $body = [
            'UPSSecurity' => [
                'UsernameToken' => [
                    'Username' => $this->credentials->getUsername(),
                    'Password' => $this->credentials->getPassword(),
                ],
                'ServiceAccessToken' => [
                    'AccessLicenseNumber' => $this->credentials->getAccessLicense(),
                ],
            ],
            'TrackRequest' => [
                'Request' => [
                    // All activities
                    'RequestOption' => '1',
                ],
                'InquiryNumber' => $trackingNumber,
            ],
        ];

        return $this->guzzle->requestAsync('POST', $this->baseUrl . '/Track', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string) $response->getBody();
            $json = json_decode($body, true);

            if (Arrays::get($json, 'TrackResponse.Shipment') === null) {
                return new TrackingResult(TrackingResult::STATUS_ERROR, $body);
            }

            $estimatedDelivery = null;
            $deliveryDetail = $json['TrackResponse']['Shipment']['DeliveryDetail'] ?? [];
            if (!empty($deliveryDetail['Type']) && 'Scheduled Delivery' === $deliveryDetail['Type']['Description']) {
                //They only supply the date so let's set time to 12 to cover most of the world
                $estimatedDelivery = \DateTime::createFromFormat('Ymd H:i:s', $deliveryDetail['Date'].' 12:00:00', new \DateTimeZone('UTC'));
            }

            // if we're tracking a multi-piece shipment
            // we assume that the first package is the
            // master package.
            $packages = $json['TrackResponse']['Shipment']['Package'] ?? [];
            $activities = [];
            if (!empty($packages)) {
                $package = Xml::isNumericKeyArray($packages) ? $packages[0] : $packages;

                $activities = $package['Activity'] ?? [];

                // if there is only one activity UPS decides to not return
                // an array of activities and instead they only list one.
                // probably because they're converting from XML.
                $activities = Xml::isNumericKeyArray($activities) ? $activities : [$activities];

                $activities = (new Collection($activities))->map(function (array $row): TrackingActivity {
                    $address = new Address(
                        '',
                        [],
                        $row['ActivityLocation']['Address']['PostalCode'] ?? '',
                        $row['ActivityLocation']['Address']['City'] ?? '',
                        '',
                        $row['ActivityLocation']['Address']['CountryCode'] ?? ''
                    );
                    $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['Date'] . $row['Time']);
                    $status = $this->getStatusFromType($row['Status']['Type']);
                    $description = $row['Status']['Description'] ?? '';
                    return new TrackingActivity($status, $description, $date, $address);
                })->value();
            }

            $tracking = new Tracking('UPS', $json['TrackResponse']['Shipment']['Service']['Description'], $activities);

            $tracking->estimatedDeliveryDate = $estimatedDelivery;

            return new TrackingResult(TrackingResult::STATUS_SUCCESS, $body, $tracking);
        });
    }

    /**
     * @param string $type
     * @return int
     */
    private function getStatusFromType(string $type): int
    {
        $type = mb_strtoupper($type, 'utf-8');
        switch ($type) {
            case 'D':
                return TrackingActivity::STATUS_DELIVERED;
            case 'I':
            case 'P':
            case 'M':
                return TrackingActivity::STATUS_IN_TRANSIT;
            case 'X':
                return TrackingActivity::STATUS_EXCEPTION;
        }
        return TrackingActivity::STATUS_IN_TRANSIT;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        // TODO: Implement createLabel() method.
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
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {
        return promise_for([]);
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

    /**
     * @return PromiseInterface
     */
    public function createPickup(PickupRequest $request): PromiseInterface
    {
        // TODO: Implement createPickup() method.
    }
}
