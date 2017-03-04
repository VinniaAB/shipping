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
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ValidatorBuilder;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;

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
     * @return PromiseInterface
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
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

            if (!$this->validateResponse($body)) {
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

    private function validateResponse(array $body): bool
    {
        $validator = (new ValidatorBuilder())->getValidator();
        $constraints = new Collection([
            'allowExtraFields' => false,
            'allowMissingFields' => false,
            'fields' => [
                'RateResponse' => new Collection([
                    'allowExtraFields' => true,
                    'allowMissingFields' => false,
                    'fields' => [
                        'RatedShipment' => new All(new Type('array')),
                    ],
                ]),
            ],
        ]);
        return count($validator->validate($body, $constraints)) === 0;
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
