<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-04
 * Time: 14:03
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\FedEx\Credentials;
use Vinnia\Shipping\FedEx\Service as FedEx;
use Vinnia\Shipping\FedEx\Service;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;
use DateTimeImmutable;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

class FedExTest extends AbstractServiceTest
{

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
        return new FedEx(new Client(), $credentials, FedEx::URL_PRODUCTION);
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
        $package = new Package(
            new Amount(30, Unit::CENTIMETER),
            new Amount(30, Unit::CENTIMETER),
            new Amount(30, Unit::CENTIMETER),
            new Amount(1, Unit::KILOGRAM)
        );
        $req = new ShipmentRequest('FEDEX_GROUND', $sender, $sender, $package);
        $promise = $this->service->createShipment($req);

        /* @var \Vinnia\Shipping\Shipment $shipment */
        $shipment = $promise->wait();

        $this->assertInstanceOf(Shipment::class, $shipment);

        var_dump($shipment);

        file_put_contents('/Users/johan/Desktop/fedex.pdf', $shipment->labelData);

        $deleted = $this->service->cancelShipment($shipment->id, ['type' => 'FEDEX'])
            ->wait();

        var_dump($deleted);
    }
}
