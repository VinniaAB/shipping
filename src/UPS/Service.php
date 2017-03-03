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
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://wwwcie.ups.com/rest';
    const URL_PRODUCTION = 'https://onlinetools.ups.com/rest';

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
    private $serviceCode;

    /**
     * @var string
     */
    private $baseUrl;

    function __construct(
        ClientInterface $guzzle,
        Credentials $credentials,
        string $serviceCode,
        string $baseUrl = self::URL_PRODUCTION
    )
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->serviceCode = $serviceCode;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface
     */
    public function getPrice(Address $sender, Address $recipient, Package $package): PromiseInterface
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
            'RateRequest' => [
                'Request' => [
                    'RequestOption' => 'Rate',
                    'TransactionReference' => [
                        'CustomerContext' => '',
                    ],
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
                    'Service' => [
                        'Code' => $this->serviceCode,
                    ],
                    'Package' => [
                        'PackagingType' => [
                            'Code' => '02',
                        ],
                        'Dimensions' => [
                            'UnitOfMeasurement' => [
                                'Code' => 'CM',
                            ],
                            'Length' => (string) $package->getLength(),
                            'Width' => (string) $package->getWidth(),
                            'Height' => (string) $package->getHeight(),
                        ],
                        'PackageWeight' => [
                            'UnitOfMeasurement' => [
                                'Code' => 'Kgs',
                            ],
                            'Weight' => (string)($package->getWeight() / 1000),
                        ],
                    ],
                    'ShipmentRatingOptions' => [
                        'NegotiatedRatesIndicator' => '',
                    ],
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
            $rules = [
                'RateResponse.Response.ResponseStatus' => 'required',
                'RateResponse.RatedShipment.TotalCharges.MonetaryValue' => 'required|numeric',
            ];
            if (validator($body, $rules)->fails()) {
                return new RejectedPromise($body);
            }
            $charges = array_get($body, 'RateResponse.RatedShipment.TotalCharges');
            return Money::fromFloat((float) $charges['MonetaryValue'], $charges['CurrencyCode']);

        });
    }

    private function validateResponse(array $body): bool
    {
        // TODO: implement
        return true;
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        throw new Exception(__METHOD__ . ' is not implemented');
    }

}
