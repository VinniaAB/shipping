<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests\DHL;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\DHL\Credentials;
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

    public function testCreateShipmentParsesResponseAndExtractsLabel()
    {
        $service = new ShipmentService(
            $this->createClient(),
            new Credentials('', '', ''),
            ShipmentService::URL_TEST
        );
        $address = Address::fromArray([
            'country_code' => 'US',
        ]);
        $request = new ShipmentRequest('', $address, $address, []);
        $this->responseQueue[] = new Response(200, [], stream_for(
            <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<Response>
  <AirwayBillNumber>MY_AWB_NUMBER</AirwayBillNumber>
  <OutputImage>TVlfTEFCRUxfSU1BR0U=</OutputImage>
</Response>
XML
        ));
        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $service->createShipment($request)
            ->wait();

        $this->assertCount(1, $shipments);
        $this->assertSame('MY_AWB_NUMBER', $shipments[0]->id);
        $this->assertSame('MY_LABEL_IMAGE', $shipments[0]->labelData);
    }

    public function testCreateShipmentFromSwitzerlandToTheUnitedKingdom()
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

        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $service->createShipment($request)
            ->wait();

        $this->assertCount(1, $shipments);
        $this->assertMatchesRegularExpression('#^\d+$#', $shipments[0]->id);
    }
}
