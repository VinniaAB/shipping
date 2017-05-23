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
use DateTimeInterface;
use DateTimeZone;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Money\Currency;
use Money\Money;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
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
     * @param array $options
     * @return PromiseInterface
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $package = $package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        // after value conversions we might get lots of decimals. deal with that
        $length = number_format($package->length->getValue(), 2, '.', '');
        $width = number_format($package->width->getValue(), 2, '.', '');
        $height = number_format($package->height->getValue(), 2, '.', '');
        $weight = number_format($package->weight->getValue(), 2, '.', '');

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
         <CountryCode>{$sender->countryCode}</CountryCode>
         <Postalcode>{$sender->zip}</Postalcode>
         <City>{$sender->city}</City>
      </From>
      <BkgDetails>
         <PaymentCountryCode>{$sender->countryCode}</PaymentCountryCode>
         <Date>{$dt->format('Y-m-d')}</Date>
         <ReadyTime>PT00H00M</ReadyTime>
         <DimensionUnit>CM</DimensionUnit>
         <WeightUnit>KG</WeightUnit>
         <Pieces>
            <Piece>
               <PieceID>1</PieceID>
               <Height>{$height}</Height>
               <Depth>{$length}</Depth>
               <Width>{$width}</Width>
               <Weight>{$weight}</Weight>
            </Piece>
         </Pieces>
         <PaymentAccountNumber>{$this->credentials->getAccountNumber()}</PaymentAccountNumber>
      </BkgDetails>
      <To>
         <CountryCode>{$recipient->countryCode}</CountryCode>
         <Postalcode>{$recipient->zip}</Postalcode>
         <City>{$recipient->city}</City>
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
     * @param array $options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
    {
        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:KnownTrackingRequest xmlns:req="http://www.dhl.com"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.dhl.com TrackingRequestKnown.xsd">
   <Request>
      <ServiceHeader>
         <SiteID>{$this->credentials->getSiteID()}</SiteID>
         <Password>{$this->credentials->getPassword()}</Password>
      </ServiceHeader>
   </Request>
   <LanguageCode>en</LanguageCode>
   <AWBNumber>{$trackingNumber}</AWBNumber>
   <LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>
   <PiecesEnabled>S</PiecesEnabled>
</req:KnownTrackingRequest>
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

            $info = $xml->xpath('/req:TrackingResponse/AWBInfo/ShipmentInfo');

            if (!$info) {
                return new RejectedPromise($body);
            }

            $activities = (new Collection($info[0]->xpath('ShipmentEvent')))->map(function (SimpleXMLElement $element) {
                $dtString = ((string) $element->{'Date'}) . ' ' . ((string) $element->{'Time'});
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dtString);

                // ServiceArea.Description is a string of format {CITY} - {COUNTRY}
                $addressParts = explode(' - ', (string) $element->{'ServiceArea'}->{'Description'});

                $address = new Address('', [], '', $addressParts[0] ?? '', '', $addressParts[1] ?? '');

                // the description will sometimes include the location too.
                $description = (string) $element->{'ServiceEvent'}->{'Description'};

                $status = $this->getStatusFromEventCode((string) $element->{'ServiceEvent'}->{'EventCode'});

                return new TrackingActivity($status, $description, $dt, $address);
            })->reverse()->value(); // DHL orders the events in ascending order, we want the most recent first.

            return new Tracking('DHL', (string) $info[0]->{'GlobalProductCode'}, $activities);
        });
    }

    /**
     * @param string $code
     * @return int
     */
    private function getStatusFromEventCode(string $code): int
    {
        $code = mb_strtoupper($code, 'utf-8');

        // status mappings stolen from keeptracker.
        // DHL doesn't really provide any documentation for the
        // meaning of these so we'll just have to wing it for now.
        $codeMap = [
            TrackingActivity::STATUS_DELIVERED => [
                'CC', 'BR', 'TP', 'DD', 'OK', 'DL', 'DM',
            ],
            TrackingActivity::STATUS_EXCEPTION => [
                'BL', 'HI', 'HO', 'AD', 'SP', 'IA', 'SI', 'ST', 'NA',
                'CI', 'CU', 'LX', 'DI', 'SF', 'LV', 'UV', 'HN', 'DP',
                'PY', 'PM', 'BA', 'CD', 'UD', 'HX', 'TD', 'CA', 'NH',
                'MX', 'SS', 'CS', 'CM', 'RD', 'RR', 'MS', 'MC', 'OH',
                'SC', 'WX',

                // returned to shipper
                'RT',
            ],
        ];

        foreach ($codeMap as $status => $codes) {
            if (in_array($code, $codes)) {
                return $status;
            }
        }

        return TrackingActivity::STATUS_IN_TRANSIT;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $now = date('c');
        $package = $request->package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        $recipientCompanyName = $request->recipient->name;
        $recipientAddressLines = (new Collection($request->recipient->lines))
            ->map(function (string $line) {
                return "<AddressLine>{$line}</AddressLine>";
            })->join("\n");

        $senderCompanyName = $request->sender->name;
        $senderAddressLines = (new Collection($request->sender->lines))
            ->map(function (string $line) {
                return "<AddressLine>{$line}</AddressLine>";
            })->join("\n");

        $countryNames = require __DIR__ . '/../../countries.php';

        $specialServices = (new Collection($request->specialServices))->map(function (string $service): string {
            return <<<EOD
<SpecialService>
  <SpecialServiceType>{$service}</SpecialServiceType>
</SpecialService>
EOD;
        })->join('');

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="5.0">
   <Request>
      <ServiceHeader>
         <MessageTime>{$now}</MessageTime>
         <MessageReference>123456789012345678901234567890</MessageReference>
         <SiteID>{$this->credentials->getSiteID()}</SiteID>
         <Password>{$this->credentials->getPassword()}</Password>
      </ServiceHeader>
   </Request>
   <RegionCode>EU</RegionCode>
   <NewShipper>N</NewShipper>
   <LanguageCode>en</LanguageCode>
   <PiecesEnabled>Y</PiecesEnabled>
   <Billing>
      <ShipperAccountNumber>{$this->credentials->getAccountNumber()}</ShipperAccountNumber>
      <ShippingPaymentType>S</ShippingPaymentType>
      <DutyPaymentType>R</DutyPaymentType>
   </Billing>
   <Consignee>
      <CompanyName>{$recipientCompanyName}</CompanyName>
      {$recipientAddressLines}
      <City>{$request->recipient->city}</City>
      <PostalCode>{$request->recipient->zip}</PostalCode>
      <CountryCode>{$request->recipient->countryCode}</CountryCode>
      <CountryName>{$countryNames[$request->recipient->countryCode]}</CountryName>
      <Contact>
         <PersonName>{$request->recipient->contactName}</PersonName>
         <PhoneNumber>{$request->recipient->contactPhone}</PhoneNumber>
      </Contact>
   </Consignee>
   <Dutiable>
      <DeclaredValue>{$request->value}</DeclaredValue>
      <DeclaredCurrency>{$request->currency}</DeclaredCurrency>
   </Dutiable>
   <ShipmentDetails>
      <NumberOfPieces>1</NumberOfPieces>
      <Pieces>
         <Piece>
            <PieceID>1</PieceID>
            <PackageType>YP</PackageType>
            <Weight>{$package->weight}</Weight>
            <Width>{$package->width}</Width>
            <Height>{$package->height}</Height>
            <Depth>{$package->length}</Depth>
         </Piece>
      </Pieces>
      <Weight>{$package->weight}</Weight>
      <WeightUnit>K</WeightUnit>
      <GlobalProductCode>{$request->service}</GlobalProductCode>
      <Date>{$request->date->format('Y-m-d')}</Date>
      <Contents>{$request->contents}</Contents>
      <DoorTo>DD</DoorTo>
      <DimensionUnit>C</DimensionUnit>
      <IsDutiable>Y</IsDutiable>
      <CurrencyCode>{$request->currency}</CurrencyCode>
   </ShipmentDetails>
   <Shipper>
      <ShipperID>{$this->credentials->getAccountNumber()}</ShipperID>
      <CompanyName>{$senderCompanyName}</CompanyName>
      {$senderAddressLines}
      <City>{$request->sender->city}</City>
      <PostalCode>{$request->sender->zip}</PostalCode>
      <CountryCode>{$request->sender->countryCode}</CountryCode>
      <CountryName>{$countryNames[$request->sender->countryCode]}</CountryName>
      <Contact>
         <PersonName>{$request->sender->contactName}</PersonName>
         <PhoneNumber>{$request->sender->contactPhone}</PhoneNumber>
      </Contact>
   </Shipper>
   {$specialServices}
   <EProcShip>N</EProcShip>
   <LabelImageFormat>PDF</LabelImageFormat>
</req:ShipmentRequest>
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
            $body = (string) $response->getBody();

            // yes, it is ridiculous to parse xml with a regex.
            // we're doing it here because SimpleXML seems to have
            // issues with non-latin characters in the DHL response
            // eg. ÅÄÖ.
            // Also, http://stackoverflow.com/a/1732454 :)
            if (preg_match('/<AirwayBillNumber>(.+)<\/AirwayBillNumber>/', $body, $matches) === 0) {
                return new RejectedPromise($body);
            }

            $number = $matches[1];

            preg_match('/<OutputImage>(.+)<\/OutputImage>/', $body, $matches);

            $data = base64_decode($matches[1]);

            return new Shipment($number, 'DHL', $data, $body);
        });
    }

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        // TODO: Implement cancelShipment() method.
    }
}
