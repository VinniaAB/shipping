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
use GuzzleHttp\Promise\PromiseInterface;
use Exception;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://wwwcie.ups.com/rest';
    const URL_PRODUCTION = 'https://onlinetools.ups.com/rest';

    // UPS only provides a service code in the response so we might need these
    const SERVICE_CODES = [
        '11' => 'Standard',
        '03' => 'Ground',
        '12' => '3 Day Select',
        '02' => '2nd Day Air',
        '59' => '2nd Day Air AM',
        '13' => 'Next Day Air Saver',
        '01' => 'Next Day Air',
        '14' => 'Next Day Air Early A.M.',
        '07' => 'Worldwide Express',
        '54' => 'Worldwide Express Plus',
        '08' => 'Worldwide Expedited',
        '65' => 'World Wide Saver',
    ];

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

    function __construct(ClientInterface $guzzle, Credentials $credentials, string $baseUrl = self::URL_PRODUCTION)
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @param array $options
     * @return PromiseInterface
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface
    {
        // UPS doesn't allow us to use SI units inside some countries
        $nonSi = in_array(mb_strtoupper($sender->getCountry(), 'utf-8'), self::NON_SI_COUNTRIES);
        $lengthUnit = $nonSi ? 'IN' : 'CM';
        $weightUnit = $nonSi ? 'LBS' : 'KGS';

        $package = $package->convertTo(
            $nonSi ? Unit::INCH : Unit::CENTIMETER,
            $nonSi ? Unit::POUND : Unit::KILOGRAM
        );

        // after value conversions we might get lots of decimals. deal with that
        $length = number_format($package->getLength()->getValue(), 2, '.', '');
        $width = number_format($package->getWidth()->getValue(), 2, '.', '');
        $height = number_format($package->getHeight()->getValue(), 2, '.', '');
        $weight = number_format($package->getWeight()->getValue(), 2, '.', '');

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
                            'AddressLine' => $sender->getLines(),
                            'City' => $sender->getCity(),
                            'StateProvinceCode' => '',
                            'PostalCode' => $sender->getZip(),
                            'CountryCode' => $sender->getCountry(),
                        ],
                    ],
                    'ShipTo' => [
                        'Name' => '',
                        'Address' => [
                            'AddressLine' => $recipient->getLines(),
                            'City' => $recipient->getCity(),
                            'StateProvinceCode' => '',
                            'PostalCode' => $recipient->getZip(),
                            'CountryCode' => $recipient->getCountry(),
                        ],
                    ],
                    'ShipFrom' => [
                        'Name' => '',
                        'Address' => [
                            'AddressLine' => $sender->getLines(),
                            'City' => $sender->getCity(),
                            'StateProvinceCode' => '',
                            'PostalCode' => $sender->getZip(),
                            'CountryCode' => $sender->getCountry(),
                        ],
                    ],
                    'Package' => [
                        'PackagingType' => [
                            'Code' => '02',
                        ],
                        'Dimensions' => [
                            'UnitOfMeasurement' => [
                                'Code' => $lengthUnit,
                            ],
                            'Length' => $length,
                            'Width' => $width,
                            'Height' => $height,
                        ],
                        'PackageWeight' => [
                            'UnitOfMeasurement' => [
                                'Code' => $weightUnit,
                            ],
                            'Weight' => $weight,
                        ],
                    ],
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

            if (!$this->arrayHasKeys($body, ['RateResponse.RatedShipment'])) {
                return new RejectedPromise($body);
            }

            return array_map(function (array $shipment): Quote {
                $charges = $shipment['TotalCharges'];
                $amount = (int) round(((float) $charges['MonetaryValue']) * pow(10, 2));
                $product = self::SERVICE_CODES[(string) $shipment['Service']['Code']] ?? 'Unknown';

                return new Quote(
                    'UPS',
                    $product,
                    new Money($amount, new Currency($charges['CurrencyCode']))
                );
            }, $body['RateResponse']['RatedShipment']);
        });
    }

    /**
     * @param array $value
     * @param string[] $keys multi-dimensional keys may be described with dot syntax, eg "car.make"
     * @return bool
     */
    private function arrayHasKeys(array $value, array $keys): bool
    {
        foreach ($keys as $key) {
            $parts = explode('.', $key);
            $slice = $value;
            foreach ($parts as $part) {
                if (!isset($slice[$part])) {
                    return false;
                }
                $slice = $slice[$part];
            }
        }
        return true;
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
            $body = json_decode((string) $response->getBody(), true);

            if (!$this->arrayHasKeys($body, ['TrackResponse.Shipment'])) {
                return new RejectedPromise($body);
            }

            $activities = $body['TrackResponse']['Shipment']['Package']['Activity'];
            $activities = (new Collection($activities))->map(function (array $row): TrackingActivity {
                $address = new Address(
                    [],
                    $row['ActivityLocation']['Address']['PostalCode'] ?? '',
                    $row['ActivityLocation']['Address']['City'] ?? '',
                    '',
                    $row['ActivityLocation']['Address']['CountryCode'] ?? ''
                );
                $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['Date'] . $row['Time']);
                $status = $this->getStatusFromType($row['Status']['Type']);
                $description = $row['Status']['Description'];
                return new TrackingActivity($status, $description, $date, $address);
            })->value();

            return new Tracking('UPS', $body['TrackResponse']['Shipment']['Service']['Description'], $activities);
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

}
