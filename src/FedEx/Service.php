<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 19:24
 */

namespace Vinnia\Shipping\FedEx;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://test';
    const URL_PRODUCTION = 'https://wsbeta.fedex.com/web-services';

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
        $body = <<<EOD
<p:Envelope xmlns:p="http://schemas.xmlsoap.org/soap/envelope/" xmlns="http://fedex.com/ws/rate/v20">
   <p:Body>
      <RateRequest>
         <WebAuthenticationDetail>
            <UserCredential>
               <Key>Input Your Information</Key>
               <Password>Input Your Information</Password>
            </UserCredential>
         </WebAuthenticationDetail>
         <ClientDetail>
            <AccountNumber>Input Your Information</AccountNumber>
            <MeterNumber>Input Your Information</MeterNumber>
         </ClientDetail>
         <TransactionDetail>
            <CustomerTransactionId>International Priority with Payment type_SENDER</CustomerTransactionId>
         </TransactionDetail>
         <Version>
            <ServiceId>crs</ServiceId>
            <Major>20</Major>
            <Intermediate>0</Intermediate>
            <Minor>0</Minor>
         </Version>
         <RequestedShipment>
            <ShipTimestamp>2014-05-23T12:34:56-06:00</ShipTimestamp>
            <DropoffType>REGULAR_PICKUP</DropoffType>
            <ServiceType>INTERNATIONAL_PRIORITY</ServiceType>
            <PackagingType>YOUR_PACKAGING</PackagingType>
            <PreferredCurrency>USD</PreferredCurrency>
            <Shipper>
               <Contact>
                  <PersonName>Input Your Information</PersonName>
                  <CompanyName>Input Your Information</CompanyName>
                  <PhoneNumber>Input Your Information</PhoneNumber>
                  <EMailAddress>sender@yahoo.com</EMailAddress>
               </Contact>
               <Address>
                  <StreetLines>Input Your Information</StreetLines>
                  <StreetLines>Input Your Information</StreetLines>
                  <City>MEMPHIS</City>
                  <StateOrProvinceCode>TN</StateOrProvinceCode>
                  <PostalCode>38101</PostalCode>
                  <CountryCode>US</CountryCode>
               </Address>
            </Shipper>
            <Recipient>
               <Contact>
                  <PersonName>Input Your Information</PersonName>
                  <CompanyName>Input Your Information</CompanyName>
                  <PhoneNumber>Input Your Information</PhoneNumber>
                  <EMailAddress>recipient@yahoo.com</EMailAddress>
               </Contact>
               <Address>
                  <StreetLines>Input Your Information</StreetLines>
                  <StreetLines>Input Your Information</StreetLines>
                  <City>RICHMOND</City>
                  <StateOrProvinceCode>BC</StateOrProvinceCode>
                  <PostalCode>V7C4v7</PostalCode>
                  <CountryCode>CA</CountryCode>
               </Address>
            </Recipient>
            <ShippingChargesPayment>
               <PaymentType>SENDER</PaymentType>
               <Payor>
                  <ResponsibleParty>
                     <AccountNumber>Input Your Information</AccountNumber>
                  </ResponsibleParty>
               </Payor>
            </ShippingChargesPayment>
            <CustomsClearanceDetail>
               <DutiesPayment>
                  <PaymentType>SENDER</PaymentType>
                  <Payor>
                     <ResponsibleParty>
                        <AccountNumber>Input Your Information</AccountNumber>
                     </ResponsibleParty>
                  </Payor>
               </DutiesPayment>
               <DocumentContent>DOCUMENTS_ONLY</DocumentContent>
               <CustomsValue>
                  <Currency>USD</Currency>
                  <Amount>100.00</Amount>
               </CustomsValue>
               <CommercialInvoice>
                  <TermsOfSale>FOB_OR_FCA</TermsOfSale>
               </CommercialInvoice>
               <Commodities>
                  <NumberOfPieces>1</NumberOfPieces>
                  <Description>ABCD</Description>
                  <CountryOfManufacture>US</CountryOfManufacture>
                  <Weight>
                     <Units>LB</Units>
                     <Value>1.0</Value>
                  </Weight>
                  <Quantity>1</Quantity>
                  <QuantityUnits>cm</QuantityUnits>
                  <UnitPrice>
                     <Currency>USD</Currency>
                     <Amount>1.000000</Amount>
                  </UnitPrice>
                  <CustomsValue>
                     <Currency>USD</Currency>
                     <Amount>100.000000</Amount>
                  </CustomsValue>
               </Commodities>
               <ExportDetail>
                  <ExportComplianceStatement>30.37(f)</ExportComplianceStatement>
               </ExportDetail>
            </CustomsClearanceDetail>
            <RateRequestTypes>LIST</RateRequestTypes>
            <PackageCount>1</PackageCount>
            <RequestedPackageLineItems>
               <SequenceNumber>1</SequenceNumber>
               <GroupNumber>1</GroupNumber>
               <GroupPackageCount>1</GroupPackageCount>
               <Weight>
                  <Units>LB</Units>
                  <Value>50.0</Value>
               </Weight>
               <Dimensions>
                  <Length>12</Length>
                  <Width>12</Width>
                  <Height>12</Height>
                  <Units>IN</Units>
               </Dimensions>
               <CustomerReferences>
                  <CustomerReferenceType>CUSTOMER_REFERENCE</CustomerReferenceType>
                  <Value>TC001_01_PT1_ST01_PK01_SNDUS_RCPCA_POS</Value>
               </CustomerReferences>
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
        ]);
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