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
use GuzzleHttp\Promise\FulfilledPromise;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
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
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     * @internal param Address $sender
     * @internal param Address $recipient
     * @internal param Package $package
     * @internal param array $options
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface
    {
        $package = $request->package->convertTo(Unit::METER, Unit::KILOGRAM);
        $length = number_format($package->length->getValue(), 2, '.', '');
        $width = number_format($package->width->getValue(), 2, '.', '');
        $height = number_format($package->height->getValue(), 2, '.', '');
        $weight = number_format($package->weight->getValue(), 2, '.', '');

        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<priceRequest>
   <appId>PC</appId>
   <appVersion>3.0</appVersion>
   <priceCheck>
      <rateId>rate2</rateId>
      <sender>
         <country>{$request->sender->countryCode}</country>
         <town>{$request->sender->city}</town>
         <postcode>{$request->sender->zip}</postcode>
      </sender>
      <delivery>
         <country>{$request->recipient->countryCode}</country>
         <town>{$request->recipient->city}</town>
         <postcode>{$request->recipient->zip}</postcode>
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
        ])->then(function (ResponseInterface $response): array {
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
     * @param array $options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
    {
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<TrackRequest locale="en_US" version="3.1">
    <SearchCriteria marketType="INTERNATIONAL" originCountry="US">
        <ConsignmentNumber>{$trackingNumber}</ConsignmentNumber>
    </SearchCriteria>
    <LevelOfDetail>
        <Complete shipment="true" />
    </LevelOfDetail>
</TrackRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl . '/track.do', [
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'auth' => [$this->credentials->getUsername(), $this->credentials->getPassword(), 'basic'],

            // yes, TNT wants url-encoded XML for this endpoint.
            'form_params' => [
                'xml_in' => $body,
            ],
        ])->then(function (ResponseInterface $response) {
            $body = (string) $response->getBody();

            $xml = new SimpleXMLElement($body, LIBXML_PARSEHUGE);

            $activities = $xml->xpath('/TrackResponse/Consignment/StatusData');

            if (!$activities) {
                return new RejectedPromise($body);
            }

            $activities = (new Collection($activities))->map(function (SimpleXMLElement $e): TrackingActivity {
                $dt = DateTimeImmutable::createFromFormat('YmdHi', ((string) $e->LocalEventDate) . ((string) $e->LocalEventTime));

                // unfortunately TNT only supplies a "Depot" and "DepotName" for the location
                // of the status update so we can't really create a good address from it.
                $address = new Address('', [], '', (string) $e->DepotName, '', '');
                $status = $this->getStatusFromCode((string) $e->StatusCode);
                $description = (string) $e->StatusDescription;
                return new TrackingActivity($status, $description, $dt, $address);
            })->value();

            $service = (string) $xml->Consignment->ShipmentSummary->Service;

            return new Tracking('TNT', $service, $activities);
        });
    }

    /**
     * @param string $code
     * @return int
     */
    private function getStatusFromCode(string $code): int
    {
        $code = mb_strtoupper($code, 'utf-8');

        // TNT provides zero documentation for these codes. YEE BOI
        switch ($code) {
            case 'OK':
                return TrackingActivity::STATUS_DELIVERED;
        }
        return TrackingActivity::STATUS_IN_TRANSIT;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        // TODO: Implement createLabel() method.
    }

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        return new FulfilledPromise(true);
    }

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {
        return promise_for([]);
    }

}
