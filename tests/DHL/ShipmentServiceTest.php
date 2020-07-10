<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests\DHL;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\DHL\Credentials;
use Vinnia\Shipping\DHL\Service;
use Vinnia\Shipping\DHL\ShipmentService;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tests\AbstractTestCase;
use Vinnia\Shipping\Tests\GuzzleTrait;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;
use function GuzzleHttp\Psr7\stream_for;

class ShipmentServiceTest extends AbstractTestCase
{
    use GuzzleTrait;

    const EMPTY_ADDRESS = [
        'country_code' => 'US',
    ];
    const EMPTY_RESPONSE = <<<TXT
<?xml version="1.0" encoding="UTF-8" ?>
<Response>
  <AirwayBillNumber>MY_AWB_NUMBER</AirwayBillNumber>
  <OutputImage>TVlfTEFCRUxfSU1BR0U=</OutputImage>
</Response>
TXT;


    public function testCreateShipmentParsesResponseAndExtractsLabel()
    {
        $service = new ShipmentService(
            $this->createClient(),
            new Credentials('', '', ''),
            ShipmentService::URL_TEST
        );
        $address = Address::fromArray(static::EMPTY_ADDRESS);
        $request = new ShipmentRequest('', $address, $address, []);
        $this->responseQueue[] = new Response(200, [], stream_for(static::EMPTY_RESPONSE));
        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $service->createShipment($request)
            ->wait();

        $this->assertCount(1, $shipments);
        $this->assertSame('MY_AWB_NUMBER', $shipments[0]->id);
        $this->assertSame('MY_LABEL_IMAGE', $shipments[0]->labelData);
    }

    public function testCreateDutiableShipmentFromSwitzerlandToTheUnitedKingdom()
    {
        $credentials = $this->getCredentialsOfName('dhl_export_switzerland');
        $service = new ShipmentService(
            new Client(),
            $credentials,
            ShipmentService::URL_TEST
        );

        $sender = new Address(
            'Some Company',
            ['Bächlerstrasse 1'],
            '8802',
            'Kilchberg',
            '',
            'CH',
            'Some Dude',
            '12345',
        );
        $recipient = new Address(
            'Some Other Company',
            ['1 Bakers Road'],
            'UB8 1RG',
            'Uxbridge',
            '',
            'GB',
            'Some Other Dude',
            '12345',
        );

        $request = new ShipmentRequest('P', $sender, $recipient, [
            Parcel::make(10.0, 10.0, 10.0, 2.0, Unit::CENTIMETER, Unit::KILOGRAM),
        ]);
        $request->exportDeclarations = [
            new ExportDeclaration('Solid cube of titanium', 'CH', 1, 100.00, 'CHF', new Amount(2.0, Unit::KILOGRAM)),
        ];
        $request->isDutiable = true;

        $this->executeCreateShipmentTest(fn () => $service->createShipment($request)->wait());
    }

    public function testCreateNonDutiableShipmentFromSwitzerlandToTheUnitedKingdom()
    {
        $credentials = $this->getCredentialsOfName('dhl_export_switzerland');
        $service = new ShipmentService(
            new Client(),
            $credentials,
            ShipmentService::URL_TEST
        );

        $sender = new Address(
            'Some Company',
            ['Bächlerstrasse 1'],
            '8802',
            'Kilchberg',
            '',
            'CH',
            'Some Dude',
            '12345',
        );
        $recipient = new Address(
            'Some Other Company',
            ['1 Bakers Road'],
            'UB8 1RG',
            'Uxbridge',
            '',
            'GB',
            'Some Other Dude',
            '12345',
        );

        $request = new ShipmentRequest('K', $sender, $recipient, [
            Parcel::make(10.0, 10.0, 10.0, 2.0, Unit::CENTIMETER, Unit::KILOGRAM),
        ]);
        $request->exportDeclarations = [
            new ExportDeclaration('Solid cube of titanium', 'CH', 1, 100.00, 'CHF', new Amount(2.0, Unit::KILOGRAM)),
        ];
        $request->isDutiable = false;

        $this->executeCreateShipmentTest(fn () => $service->createShipment($request)->wait());
    }

    public function specialServiceCodeProvider()
    {
        return [
            [['SA'], fn ($request) => $request->signatureRequired = true],
            [['II'], fn ($request) => $request->insuredValue = 10.00],
        ];
    }

    /**
     * @dataProvider specialServiceCodeProvider
     * @param string[] $expected
     * @param callable $fn
     */
    public function testAddsSpecialServiceCode(array $expected, callable $fn)
    {
        $service = new ShipmentService($this->createClient(), new Credentials('', '', ''));

        $this->responseQueue[] = new Response(200, [], stream_for(static::EMPTY_RESPONSE));
        $address = Address::fromArray(static::EMPTY_ADDRESS);
        $request = new ShipmentRequest('', $address, $address, []);

        $fn($request);

        $service->createShipment($request)->wait();
        $this->assertCount(1, $this->requests);

        $body = (string) $this->requests[0]->getBody();

        foreach ($expected as $code) {
            $this->assertStringContainsString("<SpecialServiceType>${code}</SpecialServiceType>", $body);
        }
    }

    public function testDoesNotAddDutyAccountNumbersForNonDutiableShipments()
    {
        $service = new ShipmentService($this->createClient(), new Credentials('', '', ''));

        $this->responseQueue[] = new Response(200, [], stream_for(static::EMPTY_RESPONSE));
        $address = Address::fromArray(static::EMPTY_ADDRESS);
        $request = new ShipmentRequest('', $address, $address, []);
        $request->isDutiable = false;

        $service->createShipment($request)->wait();
        $this->assertCount(1, $this->requests);

        $body = (string) $this->requests[0]->getBody();

        $this->assertStringNotContainsString('<DutyPaymentType>', $body);
        $this->assertStringNotContainsString('<DutyAccountNumber>', $body);
    }

    public function testAddsDutyAccountNumbersToDutiableShipment()
    {
        $service = new ShipmentService($this->createClient(), new Credentials('', '', '321'));

        $this->responseQueue[] = new Response(200, [], stream_for(static::EMPTY_RESPONSE));
        $address = Address::fromArray(static::EMPTY_ADDRESS);
        $request = new ShipmentRequest('', $address, $address, []);
        $request->isDutiable = true;
        $request->incoterm = 'DAT';

        $service->createShipment($request)->wait();
        $this->assertCount(1, $this->requests);

        $body = (string) $this->requests[0]->getBody();

        $this->assertStringContainsString('<DutyPaymentType>R</DutyPaymentType>', $body);

        // since the duty is paid by the recipient we don't wanna include an account number here.
        $this->assertStringNotContainsString('<DutyAccountNumber>', $body);
    }

    public function testUsesSenderDutyPaymentTypeForDDPShipments()
    {
        $service = new ShipmentService($this->createClient(), new Credentials('', '', '321'));

        $this->responseQueue[] = new Response(200, [], stream_for(static::EMPTY_RESPONSE));
        $address = Address::fromArray(static::EMPTY_ADDRESS);
        $request = new ShipmentRequest('', $address, $address, []);
        $request->isDutiable = true;
        $request->incoterm = 'DDP';

        $service->createShipment($request)->wait();
        $this->assertCount(1, $this->requests);

        $body = (string) $this->requests[0]->getBody();

        $this->assertStringContainsString('<DutyPaymentType>S</DutyPaymentType>', $body);
    }
}
