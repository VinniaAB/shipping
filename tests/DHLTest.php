<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 17:17
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\Tests;

use DateTimeImmutable;
use GuzzleHttp\Client;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\DHL\Service as DHL;
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\Package;
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
        $sender = new Address('Company AB', ['Street 1'], '111 57', 'Stockholm', '', 'SE', 'Helmut', '1234567890');
        $package = new Package(
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(1.0, Unit::METER),
            new Amount(5.0, Unit::KILOGRAM)
        );
        $req = new ShipmentRequest('Q', $sender, $sender, $package);
        $req->specialServices = ['PT'];
        $req->currency = 'EUR';

        $promise = $this->service->createShipment($req);

        /* @var Shipment $res */
        $res = $promise->wait();

        var_dump($res);

        $this->assertInstanceOf(Shipment::class, $res);
    }

}
