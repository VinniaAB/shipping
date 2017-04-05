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
use Vinnia\Shipping\Label;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
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
        $length = number_format($package->getLength()->getValue(), 2, '.', '');
        $width = number_format($package->getWidth()->getValue(), 2, '.', '');
        $height = number_format($package->getHeight()->getValue(), 2, '.', '');
        $weight = number_format($package->getWeight()->getValue(), 2, '.', '');

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
               <Height>{$height}</Height>
               <Depth>{$length}</Depth>
               <Width>{$width}</Width>
               <Weight>{$weight}</Weight>
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

                $address = new Address([], '', $addressParts[0] ?? '', '', $addressParts[1] ?? '');

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
     * @param DateTimeInterface $date
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @param array $options
     * @return PromiseInterface
     */
    public function createLabel(DateTimeInterface $date, Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $package = $package->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);

        $productCode = $options['product_code'];

        $senderContactName = $options['sender_contact_name'];
        $senderContactPhone = $options['sender_contact_phone'];

        $recipientContactName = $options['recipient_contact_name'];
        $recipientContactPhone = $options['recipient_contact_phone'];

        $recipientCompanyName = $recipient->getLines()[0];
        $recipientAddressLines = (new Collection($recipient->getLines()))
            ->tail()
            ->map(function (string $line) {
                return "<AddressLine>{$line}</AddressLine>";
            })->join("\n");

        $senderCompanyName = $sender->getLines()[0];
        $senderAddressLines = (new Collection($sender->getLines()))
            ->tail()
            ->map(function (string $line) {
                return "<AddressLine>{$line}</AddressLine>";
            })->join("\n");

        $countryNames = require __DIR__ . '/../../countries.php';

        $amount = $options['amount'];
        $currency = $options['currency'];
        $content = $options['content'];

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="5.0">
   <Request>
      <ServiceHeader>
         <MessageTime>{$dt->format('c')}</MessageTime>
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
      <City>{$recipient->getCity()}</City>
      <PostalCode>{$recipient->getZip()}</PostalCode>
      <CountryCode>{$recipient->getCountry()}</CountryCode>
      <CountryName>{$countryNames[$recipient->getCountry()]}</CountryName>
      <Contact>
         <PersonName>{$recipientContactName}</PersonName>
         <PhoneNumber>{$recipientContactPhone}</PhoneNumber>
      </Contact>
   </Consignee>
   <Dutiable>
      <DeclaredValue>{$amount}</DeclaredValue>
      <DeclaredCurrency>{$currency}</DeclaredCurrency>
   </Dutiable>
   <ShipmentDetails>
      <NumberOfPieces>1</NumberOfPieces>
      <Pieces>
         <Piece>
            <PieceID>1</PieceID>
            <PackageType>YP</PackageType>
            <Weight>{$package->getWeight()}</Weight>
            <Width>{$package->getWidth()}</Width>
            <Height>{$package->getHeight()}</Height>
            <Depth>{$package->getLength()}</Depth>
         </Piece>
      </Pieces>
      <Weight>{$package->getWeight()}</Weight>
      <WeightUnit>K</WeightUnit>
      <GlobalProductCode>{$productCode}</GlobalProductCode>
      <Date>{$date->format('Y-m-d')}</Date>
      <Contents>{$content}</Contents>
      <DoorTo>DD</DoorTo>
      <DimensionUnit>C</DimensionUnit>
      <IsDutiable>Y</IsDutiable>
      <CurrencyCode>{$currency}</CurrencyCode>
   </ShipmentDetails>
   <Shipper>
      <ShipperID>{$this->credentials->getAccountNumber()}</ShipperID>
      <CompanyName>{$senderCompanyName}</CompanyName>
      {$senderAddressLines}
      <City>{$sender->getCity()}</City>
      <PostalCode>{$sender->getZip()}</PostalCode>
      <CountryCode>{$sender->getCountry()}</CountryCode>
      <CountryName>{$countryNames[$sender->getCountry()]}</CountryName>
      <Contact>
         <PersonName>{$senderContactName}</PersonName>
         <PhoneNumber>{$senderContactPhone}</PhoneNumber>
      </Contact>
   </Shipper>
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

            return new Label($number, 'DHL', 'PDF', $data);
        });
    }
}
