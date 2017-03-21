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
use GuzzleHttp\Promise\RejectedPromise;
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
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Util\Collection;
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

        $package = $package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        // after value conversions we might get lots of decimals. deal with that
        $length = number_format($package->getLength()->getValue(), 0, '.', '');
        $width = number_format($package->getWidth()->getValue(), 0, '.', '');
        $height = number_format($package->getHeight()->getValue(), 0, '.', '');
        $weight = number_format($package->getWeight()->getValue(), 0, '.', '');

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
                  <Value>{$weight}</Value>
               </Weight>
               <Dimensions>
                  <Length>{$length}</Length>
                  <Width>{$width}</Width>
                  <Height>{$height}</Height>
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
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/track/v12">
   <soapenv:Header />
   <soapenv:Body>
      <TrackRequest>
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
            <ServiceId>trck</ServiceId>
            <Major>12</Major>
            <Intermediate>0</Intermediate>
            <Minor>0</Minor>
         </Version>
         <SelectionDetails>
            <PackageIdentifier>
               <Type>TRACKING_NUMBER_OR_DOORTAG</Type>
               <Value>{$trackingNumber}</Value>
            </PackageIdentifier>
         </SelectionDetails>
         <ProcessingOptions>INCLUDE_DETAILED_SCANS</ProcessingOptions>
      </TrackRequest>
   </soapenv:Body>
</soapenv:Envelope>
EOD;

        return $this->guzzle->requestAsync('POST', $this->url . '/track', [
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string) $response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);

            $details = $xml->xpath('/SOAP-ENV:Envelope/SOAP-ENV:Body/*[local-name()=\'TrackReply\']/*[local-name()=\'CompletedTrackDetails\']/*[local-name()=\'TrackDetails\']');

            if (!$details) {
                return new RejectedPromise($body);
            }

            $service = (string) $details[0]->{'Service'}->{'Type'};
            $events = $details[0]->xpath('*[local-name()=\'Events\']');

            $activities = (new Collection($events))->map(function (SimpleXMLElement $element) {
                $status = $this->getStatusFromEventType((string) $element->{'EventType'});
                $description = (string) $element->{'EventDescription'};
                $dt = new DateTimeImmutable((string) $element->{'Timestamp'});
                $address = new Address(
                    [],
                    (string) $element->{'Address'}->{'PostalCode'},
                    (string) $element->{'Address'}->{'City'},
                    (string) $element->{'Address'}->{'StateOrProvinceCode'},
                    (string) $element->{'Address'}->{'CountryName'}
                );

                return new TrackingActivity($status, $description, $dt, $address);
            })->sort(function (TrackingActivity $a, TrackingActivity $b) {
                return $b->getDate()->getTimestamp() <=> $a->getDate()->getTimestamp();
            })->value();

            return new Tracking('FedEx', $service, $activities);
        });
    }

    /**
     * @param string $type
     * @return int
     */
    private function getStatusFromEventType(string $type): int
    {
        $type = mb_strtoupper($type, 'utf-8');

        // status mappings stolen from keeptracker.
        $typeMap = [
            TrackingActivity::STATUS_DELIVERED => [
                'DL',
            ],
            TrackingActivity::STATUS_EXCEPTION => [
                // cancelled
                'CA',

                // general issues
                'CD', 'DY', 'DE', 'HL', 'CH', 'SE',

                // returned to shipper
                'RS',
            ],
        ];

        foreach ($typeMap as $status => $types) {
            if (in_array($type, $types)) {
                return $status;
            }
        }

        return TrackingActivity::STATUS_IN_TRANSIT;
    }

}
