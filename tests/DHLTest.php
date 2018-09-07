<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 17:17
 */
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\RejectionException;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\DHL\Service as DHL;
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\Pickup;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceException;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

class DHLTest extends AbstractServiceTest
{

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new DHLCredentials(
            $data['dhl']['site_id'],
            $data['dhl']['password'],
            $data['dhl']['account_number']
        );
        return new DHL(new Client(), $credentials, DHL::URL_TEST);
    }

    /**
     * @return string[][]
     */
    public function trackingNumberProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        return array_map(function (string $value) {
            return [$value];
        }, $data['dhl']['tracking_numbers']);
    }

    public function testCreateLabel()
    {
        $sender = new Address('Company & AB', ['Street 1'], '68182', 'Omaha', 'Nebraska', 'US', 'Helmut', '12345');
        $recipient = new Address('Company & AB', ['Street 2'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '12345');
        $package = new Parcel(
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(5.0, Unit::KILOGRAM)
        );
        $req = new ShipmentRequest('N', $sender, $recipient, [$package]);
        $req->specialServices = ['PT'];
        $req->isDutiable = true;
        $req->currency = 'EUR';
        $req->contents = 'Samples';
        $req->incoterm = 'DAP';
        $req->reference = 'ABC12345';
        $req->exportDeclarations = [
            new ExportDeclaration('Samples', 'SE', 1, 10.0, 'SEK', new Amount(1, Unit::KILOGRAM)),
        ];
        $req->insuredValue = 10.0;

        $promise = $this->service->createShipment($req);

        /* @var Shipment[] $res */
        $res = $promise->wait();

        $this->assertCount(1, $res);
    }

    public function testCreateLabelWithImperialUnits()
    {
        $sender = new Address('Company & AB', ['Street 1'], '68182', 'Omaha', 'Nebraska', 'US', 'Helmut', '12345');
        $recipient = new Address('Company & AB', ['Street 2'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '12345');
        $package = new Parcel(
            new Amount(10.0, Unit::INCH),
            new Amount(10.0, Unit::INCH),
            new Amount(10.0, Unit::INCH),
            new Amount(5.0, Unit::POUND)
        );
        $req = new ShipmentRequest('Q', $sender, $recipient, [$package]);
        $req->specialServices = ['PT'];
        $req->isDutiable = true;
        $req->currency = 'USD';
        $req->contents = 'Samples';
        $req->incoterm = 'DAP';
        $req->reference = 'ABC12345';
        $req->exportDeclarations = [
            new ExportDeclaration('Samples', 'US', 1, 10.0, 'USD', new Amount(5.0, Unit::POUND)),
        ];
        $req->units = ShipmentRequest::UNITS_IMPERIAL;

        $promise = $this->service->createShipment($req);

        /* @var Shipment[] $res */
        $res = $promise->wait();

        $this->assertCount(1, $res);
    }

    public function testQuoteError()
    {

        $sender = new Address('', [], '', '', '', '', '', '');
        $package = new Parcel(
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(5.0, Unit::KILOGRAM)
        );

        $request = new QuoteRequest($sender, $sender, [$package]);

        $this->expectException(ServiceException::class);
        $this->service->getQuotes($request)
            ->wait();
    }

    public function testCreateShipmentError()
    {
        $sender = new Address('', [], '', '', '', 'US', '', '');
        $package = new Parcel(
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(5.0, Unit::KILOGRAM)
        );

        $this->expectException(ServiceException::class);
        $this->service->createShipment(new ShipmentRequest('', $sender, $sender, [$package]))
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

    public function testGetProofOfDeliveryThrowsError()
    {
        $this->expectException(\Exception::class);
        $this->service->getProofOfDelivery('123');
    }

    public function testCreateDhlPickup()
    {

        $pickupAddress = new Address('Helmut Inc.', ['Road 1'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');
        $requestoAddress = new Address('Helmut Inc.', ['Road 1'], '68183', 'Omaha', 'Nebraska', 'US', 'Helmut', '123456');

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

        $request = new PickupRequest(
            'P',
            $pickupAddress,
            $requestoAddress,
            $from,
            $to,
            $parcels
        );

        $promise = $this->service->createPickup($request);

        $result = $promise->wait();

        $this->assertInstanceOf(Pickup::class, $result);
        $this->assertEquals('DHL', $result->vendor);
        $this->assertNotEmpty($result->raw);
    }

}
