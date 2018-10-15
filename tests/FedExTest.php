<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-04
 * Time: 14:03
 */
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\ErrorFormatterInterface;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\FedEx\Credentials;
use Vinnia\Shipping\FedEx\Service as FedEx;
use Vinnia\Shipping\Pickup;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\ProofOfDeliveryResult;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\ExactErrorFormatter;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

class FedExTest extends AbstractServiceTest
{
    /**
     * @var ResponseInterface
     */
    private $mockResponse;

    /**
     * @var string
     */
    private $mockProofOfDeliveryResponseBody = <<<EOD
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Header/><SOAP-ENV:Body><GetTrackingDocumentsReply xmlns="http://fedex.com/ws/track/v14"><HighestSeverity>SUCCESS</HighestSeverity><Notifications><Severity>SUCCESS</Severity><Source>trck</Source><Code>0</Code><Message>Request was successfully processed.</Message><LocalizedMessage>Request was successfully processed.</LocalizedMessage></Notifications><Version><ServiceId>trck</ServiceId><Major>14</Major><Intermediate>0</Intermediate><Minor>0</Minor></Version><Documents><Type>SIGNATURE_PROOF_OF_DELIVERY</Type><ImageType>PDF</ImageType><Resolution>0</Resolution><Parts><SequenceNumber>0</SequenceNumber><Content>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MSAzIDAgUi9pbWcwIDEgMCBSPj4vUHJvY1NldCBbL1BERiAvVGV4dCAvSW1hZ2VCIC9JbWFnZUMgL0ltYWdlSV0vRm9udDw8L0YxIDIgMCBSPj4+Pi9NZWRpYUJveFswIDAgNTk1IDg0Ml0+PgplbmRvYmoKMiAwIG9iajw8L0Jhc2VGb250L0hlbHZldGljYS9UeXBlL0ZvbnQvRW5jb2RpbmcvV2luQW5zaUVuY29kaW5nL1N1YnR5cGUvVHlwZTE+PgplbmRvYmoKNSAwIG9iajw8L1R5cGUvUGFnZXMvQ291bnQgMS9LaWRzWzYgMCBSXT4+CmVuZG9iago3IDAgb2JqPDwvVHlwZS9DYXRhbG9nL1BhZ2VzIDUgMCBSPj4KZW5kb2JqCjggMCBvYmo8PC9Qcm9kdWNlcihpVGV4dDEuMiBieSBsb3dhZ2llLmNvbSBcKGJhc2VkIG9uIGl0ZXh0LXBhdWxvLTE0OFwpKS9Nb2REYXRlKEQ6MjAxODA2MTIxNDIwMDNaKS9DcmVhdGlvbkRhdGUoRDoyMDE4MDYxMjE0MjAwM1opPj4KZW5kb2JqCnhyZWYKMCA5CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDEwMDE3IDAwMDAwIG4gCjAwMDAwMDM2ODAgMDAwMDAgbiAKMDAwMDAwODcyOSAwMDAwMCBuIAowMDAwMDEwMTA0IDAwMDAwIG4gCjAwMDAwMDk4MjcgMDAwMDAgbiAKMDAwMDAxMDE1NCAwMDAwMCBuIAowMDAwMDEwMTk4IDAwMDAwIG4gCnRyYWlsZXIKPDwvUm9vdCA3IDAgUi9JRCBbPGI5YTFhMmVlMjJjZTQ4NjI1OTVmZTMwOTIzODUwNzU2PjxiOWExYTJlZTIyY2U0ODYyNTk1ZmUzMDkyMzg1MDc1Nj5dL0luZm8gOCAwIFIvU2l6ZSA5Pj4Kc3RhcnR4cmVmCjEwMzM5CiUlRU9GCg==</Content></Parts></Documents></GetTrackingDocumentsReply></SOAP-ENV:Body></SOAP-ENV:Envelope>
EOD;

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        $c = require __DIR__ . '/../credentials.php';
        $credentials = new Credentials(
            $c['fedex']['credential_key'],
            $c['fedex']['credential_password'],
            $c['fedex']['account_number'],
            $c['fedex']['meter_number']
        );
        return new FedEx(new Client(), $credentials, FedEx::URL_TEST);
    }

    /**
     * @return string[][]
     */
    public function trackingNumberProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        return array_map(function (string $value) {
            return [$value];
        }, $data['fedex']['tracking_numbers']);
    }

    public function testCreateLabel()
    {
        $sender = new Address('Helmut Inc.', ['Road 1'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');
        $recipient = new Address('Helmut Inc.', ['Road 2'], '100 00', 'Stockholm', '', 'SE', 'Helmut', '123456');
        $package = new Parcel(
            new Amount(30, Unit::CENTIMETER),
            new Amount(30, Unit::CENTIMETER),
            new Amount(30, Unit::CENTIMETER),
            new Amount(1, Unit::KILOGRAM)
        );
        $req = new ShipmentRequest('INTERNATIONAL_ECONOMY', $sender, $recipient, [$package]);
        $req->reference = 'ABC12345';
        $req->exportDeclarations = [
            new ExportDeclaration('Shoes', 'US', 2, 100.00, 'USD', new Amount(1.0, Unit::KILOGRAM)),
        ];
        $req->currency = 'USD';

        $promise = $this->service->createShipment($req);

        /* @var \Vinnia\Shipping\Shipment[] $shipment */
        $shipments = $promise->wait();
        $shipment = $shipments[0];

        $this->assertCount(1, $shipments);

        $this->assertInstanceOf(Shipment::class, $shipment);

        $this->assertNotEmpty($shipment->labelData);

        $this->service->cancelShipment($shipment->id, ['type' => 'FEDEX'])
            ->wait();
    }

    public function testCreateLabelWithImperialUnits()
    {
        $sender = new Address('Helmut Inc.', ['Road 1'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');
        $recipient = new Address('Helmut Inc.', ['Road 2'], '100 00', 'Stockholm', '', 'SE', 'Helmut', '123456');
        $package = new Parcel(
            new Amount(5, Unit::INCH),
            new Amount(5, Unit::INCH),
            new Amount(5, Unit::INCH),
            new Amount(1, Unit::POUND)
        );
        $req = new ShipmentRequest('INTERNATIONAL_ECONOMY', $sender, $recipient, [$package]);
        $req->reference = 'ABC12345';
        $req->exportDeclarations = [
            new ExportDeclaration('Shoes', 'US', 2, 100.00, 'USD', new Amount(1.0, Unit::POUND)),
        ];
        $req->currency = 'USD';
        $req->units = ShipmentRequest::UNITS_IMPERIAL;
        $req->signatureRequired = true;
        $req->insuredValue = 10;
        $req->value = 10;
        $req->incoterm = 'DDP';

        $promise = $this->service->createShipment($req);

        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $promise->wait();

        $this->assertNotEmpty($shipments[0]->labelData);

        $this->service->cancelShipment($shipments[0]->id, ['type' => 'FEDEX'])
            ->wait();
    }

    public function testGetAvailableServices()
    {
        $sender = new Address('Company & AB', ['Street 1'], '11157', 'Stockholm', '', 'SE', 'Helmut', '1234567890');
        $recipient = new Address('Company & AB', ['Street 2'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '12345');
        $package = new Parcel(
            new Amount(10.0, Unit::INCH),
            new Amount(10.0, Unit::INCH),
            new Amount(10.0, Unit::INCH),
            new Amount(5.0, Unit::POUND)
        );
        $req = new QuoteRequest($recipient, $sender, [$package]);
        $services = $this->service->getAvailableServices($req)
            ->wait();

        $this->assertNotEmpty($services);
    }

    public function testCreateMultiPieceShipment()
    {
        $sender = new Address('Helmut Inc.', ['Road 1'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');
        $recipient = new Address('Helmut Inc.', ['Road 2'], '100 00', 'Stockholm', '', 'SE', 'Helmut', '123456');
        $parcels = [
            new Parcel(
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(1, Unit::KILOGRAM)
            ),
            new Parcel(
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(1, Unit::KILOGRAM)
            ),
        ];
        $req = new ShipmentRequest('INTERNATIONAL_ECONOMY', $sender, $recipient, $parcels);
        $req->reference = 'ABC12345';
        $req->exportDeclarations = [
            new ExportDeclaration('Shoes', 'US', 2, 100.00, 'USD', new Amount(1.0, Unit::KILOGRAM)),
        ];
        $req->currency = 'USD';

        $promise = $this->service->createShipment($req);

        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $promise->wait();

        $this->assertCount(2, $shipments);

        $this->service->cancelShipment($shipments[0]->id, ['type' => 'FEDEX'])
            ->wait();
    }

    public function testGetProofOfDeliveryWhichExists()
    {

        $c = require __DIR__ . '/../credentials.php';
        $credentials = new Credentials(
            $c['fedex']['credential_key'],
            $c['fedex']['credential_password'],
            $c['fedex']['account_number'],
            $c['fedex']['meter_number']
        );

        $this->mockResponse = new Response(200, [], $this->mockProofOfDeliveryResponseBody);

        $guzzle = new Client([
            'handler' => HandlerStack::create(function (RequestInterface $request, array $options = []) {
                return promise_for($this->mockResponse);
            }),
        ]);

        $service = new FedEx($guzzle, $credentials, FedEx::URL_TEST);

        $promise = $service->getProofOfDelivery('123');

        $result = $promise->wait();

        $this->assertInstanceOf(ProofOfDeliveryResult::class, $result);
        $this->assertEquals(ProofOfDeliveryResult::STATUS_SUCCESS, $result->status);
        $this->assertNotNull($result->document);
    }

    public function testGetProofOfDeliveryWhichDoesNotExist()
    {
        $c = require __DIR__ . '/../credentials.php';
        $credentials = new Credentials(
            $c['fedex']['credential_key'],
            $c['fedex']['credential_password'],
            $c['fedex']['account_number'],
            $c['fedex']['meter_number']
        );

        $this->mockResponse = new Response(200, [], str_replace(
                '<Notifications><Severity>SUCCESS</Severity>',
                '<Notifications><Severity>ERROR</Severity>',
                $this->mockProofOfDeliveryResponseBody)
        );

        $guzzle = new Client([
            'handler' => HandlerStack::create(function (RequestInterface $request, array $options = []) {
                return promise_for($this->mockResponse);
            }),
        ]);

        $service = new FedEx($guzzle, $credentials, FedEx::URL_TEST);

        $promise = $service->getProofOfDelivery('123');

        $result = $promise->wait();

        $this->assertInstanceOf(ProofOfDeliveryResult::class, $result);
        $this->assertEquals(ProofOfDeliveryResult::STATUS_ERROR, $result->status);
        $this->assertNull($result->document);
    }

    public function testCreateFedexPickup()
    {
        $request = $this->createMockPickupRequest();

        $request->units = PickupRequest::UNITS_IMPERIAL;

        $promise = $this->service->createPickup($request);

        /** @var Pickup $result */
        $result = $promise->wait();

        $this->assertInstanceOf(Pickup::class, $result);
        $this->assertTrue(ctype_digit($result->id));
        $this->assertEquals($request->service, $result->service);
        $this->assertEquals($request->earliestPickup->format('c'), $result->date->format('c'));
        $this->assertEquals('OMAA', $result->locationCode);
        $this->assertEquals('FedEx', $result->vendor);
        $this->assertNotEmpty($result->raw);
    }

    public function testCancelFedexPickup()
    {

        $request = $this->createMockPickupRequest();

        $request->units = PickupRequest::UNITS_IMPERIAL;

        /** @var Pickup $pickup */
        $pickup = $this->service->createPickup($request)->wait();

        $promise = $this->service->cancelPickup(new CancelPickupRequest(
            $pickup->id,
            $pickup->service,
            $request->requestorAddress,
            $request->pickupAddress,
            $pickup->date,
            $pickup->locationCode
        ));

        $result = $promise->wait();

        $this->assertTrue($result);
    }

    public function testFedexErrorFormatter()
    {

        $c = require __DIR__ . '/../credentials.php';
        $credentials = new Credentials(
            $c['fedex']['credential_key'],
            $c['fedex']['credential_password'],
            $c['fedex']['account_number'],
            $c['fedex']['meter_number']
        );

        $formatter = new class implements ErrorFormatterInterface
        {

            public function format(string $message): string
            {
                switch ($message) {
                    case 'Package access needed':
                        return 'Pickup time possibly too narrow';
                }
                return $message;
            }
        };

        $service = new FedEx(new Client(), $credentials, FedEx::URL_TEST, $formatter);

        $request = $this->createMockPickupRequest();

        $now = new \DateTimeImmutable('now', new \DateTimeZone('America/New_York'));

        $request->earliestPickup = $now; //$now->sub(new \DateInterval("PT3H"));
        $request->latestPickup = $now->add(new \DateInterval("PT1M"));

        $this->expectException('Vinnia\Shipping\ServiceException');

        try {
            $service->createPickup($request)->wait();
        } catch (ServiceException $e) {

            $this->assertEquals('Pickup time possibly too narrow', $e->getMessage());
            throw $e;
        }
    }

    private function createMockPickupRequest(): PickupRequest
    {
        $requestorAddress = new Address('Helmut Inc.', ['Road 12'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');
        $pickupAddress = new Address('Helmut Inc.', ['Road 12'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');

        $from = new \DateTimeImmutable();
        $to = $from->add(new \DateInterval("PT3H"));

        $parcels = [
            new Parcel(
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(30, Unit::CENTIMETER),
                new Amount(2, Unit::KILOGRAM)
            )
        ];

        return new PickupRequest(
            'FDXE',
            $requestorAddress,
            $pickupAddress,
            $from,
            $to,
            $parcels
        );
    }

}
