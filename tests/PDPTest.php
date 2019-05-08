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
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\PDP\Service as PDP;
use Vinnia\Shipping\PDP\Credentials as PDPCredentials;
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

class PDPTest extends AbstractServiceTest
{

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new PDPCredentials(
            $data['pdp']['password'],

        );
        return new PDP(new Client(), $credentials, DHL::URL_TEST);
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



}
