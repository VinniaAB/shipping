<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 13:01
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\DHL;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;

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
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface promise resolved with an \App\Money object on success
     */
    public function getPrice(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        $dt = date('c');
        $weight = $package->getWeight() / 1000; // weight in kilos

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<request:DCTRequest xmlns:xsd="http://www.w3.org/2001/XMLSchema"
	xmlns:request="http://www.dhl.com"
	xmlns:dct="http://www.dhl.com/DCTRequestdatatypes"
	xmlns:dhl="http://www.dhl.com/datatypes">
	<GetQuote>
		<Request>
			<ServiceHeader>
				<MessageTime>{$dt}</MessageTime>

				<SiteID>{$this->credentials->getSiteID()}</SiteID>
				<Password>{$this->credentials->getPassword()}</Password>
			</ServiceHeader>
		</Request>
		<From>
			<CountryCode>{$sender->getCountry()}</CountryCode>
			<Postalcode>{$sender->getZip()}</Postalcode>
			<City>{$sender->getCountry()}</City>
		</From>
		<BkgDetails>
			<PaymentCountryCode>{$sender->getCountry()}</PaymentCountryCode>
			<Date>{$dt}</Date>
			<ReadyTime>PT00H00M</ReadyTime>
			<ReadyTimeGMTOffset>+00:00</ReadyTimeGMTOffset>
			<DimensionUnit>CM</DimensionUnit>
			<WeightUnit>KG</WeightUnit>
			<NumberOfPieces>1</NumberOfPieces>
			<ShipmentWeight>{$weight}</ShipmentWeight>
			<Volume>{$package->getVolume()}</Volume>
			<PaymentAccountNumber></PaymentAccountNumber>
			<IsDutiable>N</IsDutiable>
			<NetworkTypeCode>TD</NetworkTypeCode>
		</BkgDetails>
		<To>
			<CountryCode>{$recipient->getCountry()}</CountryCode>
			<Postalcode>{$recipient->getZip()}</Postalcode>
			<City>{$recipient->getCity()}</City>
		</To>
	</GetQuote>
</request:DCTRequest>
EOD;

        echo $body . PHP_EOL;

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
            return $body;
        });
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        throw new \Exception(__METHOD__ . ' is not implemented');
    }

}
