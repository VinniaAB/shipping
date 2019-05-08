<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 19:24
 */
declare(strict_types=1);

namespace Vinnia\Shipping\DPD;

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
use Money\Currency;
use Money\Money;
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
use Vinnia\Shipping\Xml;
use Vinnia\Util\Arrays;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;
use Vinnia\Util\Validation\Validator;

define('ENCODING_CDATA', 1);
define('ENCODING_HTML', 2);
class Service implements ServiceInterface
{
    const URL_TEST = 'https://test';
    const URL_PRODUCTION = 'http://api.interlinkexpress.com';
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
    public function getQuotes(QuoteRequest $request): PromiseInterface
    {

    }
    public function getTrackingStatus(array $trackingNumbers, array $options = []): PromiseInterface
    {

    }
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $now = new \DateTimeImmutable('now');
        $totalWeight = array_reduce($request->parcels, function (Amount $carry, Parcel $current) use ($request): Amount {
            $parcel = $request->units == QuoteRequest::UNITS_IMPERIAL ?
                $current->convertTo(Unit::INCH, Unit::POUND) :
                $current->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

            return new Amount($carry->getValue() + $parcel->weight->getValue(), $parcel->weight->getUnit());
        }, new Amount(0, ''));
        $data = [
                    {
                        "job_id": null,
                        "collectionOnDelivery": false,
                        "invoice": null,
                        "collectionDate": $now,
                        "consolidate": true,
                        "consignment": [
                            {
                                "consignmentNumber": null,
                                "consignmentRef": null,
                                "parcels": [],
                                "collectionDetails": {
                                    "contactDetails": {
                                        "contactName": Xml::cdata($request->sender->contactName),
                                        "telephone": Xml::cdata($request->sender->contactPhone),
                                    },
                                    "address": {
                                        "organisation": Xml::cdata($request->sender->name),
                                        "countryCode": $request->sender->countryCode,
                                        "postcode": $request->sender->zip,
                                        "street": array_map([Xml::class, 'cdata'], $request->sender->lines),
                                        "locality": "",
                                        "town": Xml::cdata($request->sender->city),
                                        "county": $countryNames[$request->sender->countryCode]
                                    }
                                },
                                "deliveryDetails": {
                                    "contactDetails": {
                                        "contactName": Xml::cdata($request->recipient->contactName),
                                        "telephone": Xml::cdata($request->recipient->contactPhone)
                                    },
                                    "address": {
                                        "organisation": Xml::cdata($request->recipient->name),
                                        "countryCode": $request->recipient->countryCode,
                                        "postcode": $request->recipient->zip,
                                        "street": array_map([Xml::class, 'cdata'], array_filter($request->recipient->lines)),
                                        "locality": "",
                                        "town": Xml::cdata($request->recipient->city),
                                        "county": $countryNames[$request->recipient->countryCode]
                                    },
                                    "notificationDetails": {
                                        "email": "",
                                        "mobile": Xml::cdata($request->recipient->contactPhone)
                                    }
                                },
                                "networkCode": "",
                                "numberOfParcels": count($request->parcels),
                                "totalWeight": $totalWeight,
                                "shippingRef1": "",
                                "shippingRef2": "",
                                "shippingRef3": "",
                                "customsValue": number_format($request->value, 2, '.', ''),
                                "deliveryInstructions": "",
                                "parcelDescription": "",
                                "liabilityValue": null,
                                "liability": false
                            }
                        ]
                    }
                ]
        $data = Xml::removeKeysWithEmptyValues($data);
        $shipmentRequest = Xml::fromArray($data);
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:BookPURequest xmlns:req="http://www.dpd.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dpd.com pickup-global-req.xsd" schemaVersion="3.0">
{$shipmentRequest}
</req:BookPURequest>
EOD;

    return $this->guzzle->requestAsync('POST', $this->baseUrl.'/shipping/shipment', [
        'query' => [
            'isUTF8Support' => true,
        ],
        'headers' => [
            'Content-Type'=> 'application/json',
            'Accept'=> 'application/json',
            'GeoClient'=> $this->credentials->getUsername().'/'.$this->credentials->getPassword(),
            'GeoSession'=> $this->login(),
            'Content-Length'=> 2416
        ],
        'body' => $body,
    ])->then(function (ResponseInterface $response) {
        $body = (string)$response->getBody();

        // yes, it is ridiculous to parse xml with a regex.
        // we're doing it here because SimpleXML seems to have
        // issues with non-latin characters in the DPD response
        // eg. ÅÄÖ.
        // Also, http://stackoverflow.com/a/1732454 :)
        if (preg_match('/<AirwayBillNumber>(.+)<\/AirwayBillNumber>/', $body, $matches) === 0) {
            $this->throwError($body);
        }

        $number = $matches[1];

        preg_match('/<OutputImage>(.+)<\/OutputImage>/', $body, $matches);

        $data = base64_decode($matches[1]);

        return [new Shipment($number, 'DPD', $data, $body)];
    });



    }
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {

    }
    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {

    }
    public function getProofOfDelivery(string $trackingNumber): PromiseInterface
    {

    }
    public function createPickup(PickupRequest $request): PromiseInterface
    {

    }
    public function cancelPickup(CancelPickupRequest $request): PromiseInterface
    {

    }
    public function login():string {
        $this->guzzle->requestAsync('POST', $this->baseUrl.'/user/?action=login', [
            'query' => [
                'isUTF8Support' => true,
            ],
            'headers' => [
                'Content-Type'=> 'application/json',
                'Accept'=> 'application/json',
                'Authorization'=> base64_encode($this->credentials->getUsername().':'.$this->credentials->getPassword()),

            ],

        ])->then(function (ResponseInterface $response) {

            if($response.error===null){
                return $response.data.geoSession;
            }
            return '';
        });
    }

}