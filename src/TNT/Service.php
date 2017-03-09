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
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Util\Measurement\Unit;

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
      <product>
         <type>N</type>
      </product>
      <priceBreakDown>true</priceBreakDown>
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
            // TODO: parse the response yee
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
