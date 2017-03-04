<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 13:01
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\DHL;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Unit;

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
     * @return PromiseInterface
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<p:DCTRequest xmlns:p="http://www.dhl.com"
    xmlns:p1="http://www.dhl.com/datatypes"
    xmlns:p2="http://www.dhl.com/DCTRequestdatatypes"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.dhl.com DCT-req.xsd ">
   <GetQuote>
      <Request>
         <ServiceHeader>
            <MessageTime>{$dt->format('c')}</MessageTime>
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
         <Date>{$dt->format('Y-m-d')}</Date>
         <ReadyTime>PT00H00M</ReadyTime>
         <DimensionUnit>CM</DimensionUnit>
         <WeightUnit>KG</WeightUnit>
         <Pieces>
            <Piece>
               <PieceID>1</PieceID>
               <Height>{$package->getHeight()->convertTo(Unit::CENTIMETER)}</Height>
               <Depth>{$package->getLength()->convertTo(Unit::CENTIMETER)}</Depth>
               <Width>{$package->getWidth()->convertTo(Unit::CENTIMETER)}</Width>
               <Weight>{$package->getWeight()->convertTo(Unit::KILOGRAM)}</Weight>
            </Piece>
         </Pieces>
         <PaymentAccountNumber>{$this->credentials->getAccountNumber()}</PaymentAccountNumber>
      </BkgDetails>
      <To>
         <CountryCode>{$recipient->getCountry()}</CountryCode>
         <Postalcode>{$recipient->getZip()}</Postalcode>
         <City>{$recipient->getCity()}</City>
      </To>
   </GetQuote>
</p:DCTRequest>
EOD;

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

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);
            $qtdShip = $xml->xpath('/res:DCTResponse/GetQuoteResponse/BkgDetails/QtdShp');

            if (count($qtdShip) === 0) {
                return new RejectedPromise($body);
            }

            $qtdShip =  new Collection($qtdShip);

            // somestimes the DHL api responds with a correct response
            // without ShippingCharge values which is strange.
            return $qtdShip->filter(function (SimpleXMLElement $element): bool {
                $charge = (string) $element->{'ShippingCharge'};
                return $charge !== '';
            })->map(function (SimpleXMLElement $element): Quote {
                $amountString = (string) $element->{'ShippingCharge'};

                // the amount is a decimal string, deal with that
                $amount = (int) round(((float)$amountString) * pow(10, 2));

                $product = (string) $element->{'ProductShortName'};

                return new Quote('DHL', $product, new Money($amount, new Currency((string) $element->{'CurrencyCode'})));
            })->value();
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
