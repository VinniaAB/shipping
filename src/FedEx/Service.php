<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 19:24
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\FedEx;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use DateTimeImmutable;
use DateTimeZone;
use SimpleXMLElement;
use Vinnia\Util\Measurement\Unit;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://test';
    const URL_PRODUCTION = 'https://gateway.fedex.com/web-services';

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
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        $fromLines = implode("\n", array_map(function (string $line): string {
            return sprintf('<StreetLines>%s</StreetLines>', $line);
        }, $sender->getLines()));

        $toLines = implode("\n", array_map(function (string $line): string {
            return sprintf('<StreetLines>%s</StreetLines>', $line);
        }, $recipient->getLines()));

        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/rate/v20">
   <p:Body>
      <RateRequest>
         <WebAuthenticationDetail>
            <UserCredential>
               <Key>{$this->credentials->getCredentialKey()}</Key>
               <Password>{$this->credentials->getCredentialPassword()}</Password>
            </UserCredential>
         </WebAuthenticationDetail>
         <ClientDetail>
            <AccountNumber>{$this->credentials->getAccountNumber()}</AccountNumber>
            <MeterNumber>{$this->credentials->getMeterNumber()}</MeterNumber>
         </ClientDetail>
         <Version>
            <ServiceId>crs</ServiceId>
            <Major>20</Major>
            <Intermediate>0</Intermediate>
            <Minor>0</Minor>
         </Version>
         <RequestedShipment>
            <DropoffType>REGULAR_PICKUP</DropoffType>
            <PackagingType>YOUR_PACKAGING</PackagingType>
            <Shipper>
               <Address>
                  {$fromLines}
                  <City>{$sender->getCity()}</City>
                  <StateOrProvinceCode>{$sender->getState()}</StateOrProvinceCode>
                  <PostalCode>{$sender->getZip()}</PostalCode>
                  <CountryCode>{$sender->getCountry()}</CountryCode>
               </Address>
            </Shipper>
            <Recipient>
               <Address>
                  {$toLines}
                  <City>{$recipient->getCity()}</City>
                  <StateOrProvinceCode>{$recipient->getState()}</StateOrProvinceCode>
                  <PostalCode>{$recipient->getZip()}</PostalCode>
                  <CountryCode>{$recipient->getCountry()}</CountryCode>
               </Address>
            </Recipient>
            <ShippingChargesPayment>
               <PaymentType>SENDER</PaymentType>
            </ShippingChargesPayment>
            <RateRequestTypes>NONE</RateRequestTypes>
            <PackageCount>1</PackageCount>
            <RequestedPackageLineItems>
               <SequenceNumber>1</SequenceNumber>
               <GroupNumber>1</GroupNumber>
               <GroupPackageCount>1</GroupPackageCount>
               <Weight>
                  <Units>KG</Units>
                  <Value>{$package->getWeight()->convertTo(Unit::KILOGRAM)}</Value>
               </Weight>
               <Dimensions>
                  <Length>{$package->getLength()->convertTo(Unit::CENTIMETER)}</Length>
                  <Width>{$package->getWidth()->convertTo(Unit::CENTIMETER)}</Width>
                  <Height>{$package->getHeight()->convertTo(Unit::CENTIMETER)}</Height>
                  <Units>CM</Units>
               </Dimensions>
            </RequestedPackageLineItems>
         </RequestedShipment>
      </RateRequest>
   </p:Body>
</p:Envelope>
EOD;

        return $this->guzzle->requestAsync('POST', $this->url . '/rate', [
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
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
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        // TODO: Implement getTrackingStatus() method.
    }
}