<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-09
 * Time: 00:40
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\TNT;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\Tracking;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;
use DateTimeImmutable;
use DateTimeZone;
use SimpleXMLElement;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://test';
    const URL_PRODUCTION = 'https://express.tnt.com/expressconnect';

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
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        $package = $package->convertTo(Unit::METER, Unit::KILOGRAM);
        $length = number_format($package->getLength()->getValue(), 2, '.', '');
        $width = number_format($package->getWidth()->getValue(), 2, '.', '');
        $height = number_format($package->getHeight()->getValue(), 2, '.', '');
        $weight = number_format($package->getWeight()->getValue(), 2, '.', '');

        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<priceRequest>
   <appId>PC</appId>
   <appVersion>3.0</appVersion>
   <priceCheck>
      <rateId>rate2</rateId>
      <sender>
         <country>{$sender->getCountry()}</country>
         <town>{$sender->getCity()}</town>
         <postcode>{$sender->getZip()}</postcode>
      </sender>
      <delivery>
         <country>{$recipient->getCountry()}</country>
         <town>{$recipient->getCity()}</town>
         <postcode>{$recipient->getZip()}</postcode>
      </delivery>
      <collectionDateTime>{$dt->format('c')}</collectionDateTime>
      <product>
         <type>N</type>
      </product>
      <account>
        <accountNumber>{$this->credentials->getAccountNumber()}</accountNumber>
        <accountCountry>{$this->credentials->getAccountCountry()}</accountCountry>
      </account>
      <currency>EUR</currency>
      <priceBreakDown>false</priceBreakDown>
      <pieceLine>
         <numberOfPieces>1</numberOfPieces>
         <pieceMeasurements>
            <length>{$length}</length>
            <width>{$width}</width>
            <height>{$height}</height>
            <weight>{$weight}</weight>
         </pieceMeasurements>
         <pallet>false</pallet>
      </pieceLine>
   </priceCheck>
</priceRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl . '/pricing/getprice', [
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml'
            ],
            'auth' => [$this->credentials->getUsername(), $this->credentials->getPassword(), 'basic'],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string) $response->getBody();
            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $services = $xml->xpath('/document/priceResponse/ratedServices/ratedService');

            return (new Collection($services))->map(function (SimpleXMLElement $element): Quote {
                $amount = ((float) ((string) $element->totalPrice)) * pow(10, 2);

                // TODO: fix hard coded currency. the currency should probably be a
                // property of the credentials since TNT requires a manually specified
                // currency.
                $money = new Money($amount, new Currency('EUR'));

                return new Quote('TNT', (string) $element->product->productDesc, $money);
            })->value();
        });
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        // TODO: Implement getTrackingStatus() method.
    }

}
