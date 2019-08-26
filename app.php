<?php
declare(strict_types = 1);

require __DIR__ . '/vendor/autoload.php';

use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\DHL\Service as DHLService;
use GuzzleHttp\Client;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\Parcel;
use Vinnia\Util\Measurement\Unit;

$c = new DHLCredentials('DeliveryServ', 'yYZUE1eJHR', '964628131');
$s = new DHLService(new Client(), $c, DHLService::URL_TEST);

$requestor = Address::fromArray([
    'name' => 'YSDS',
    'lines' =>
        array (
            0 => '647 W 27th Street',
        ),
    'zip' => '10001',
    'city' => 'New York',
    'state' => 'NY',
    'country_code' => 'US',
    'contact_name' => 'YSDS',
    'contact_phone' => '8777668080',
    'contact_email' => '',
]);
$address = Address::fromArray([
    'name' => 'YSDS',
    'lines' =>
        array (
            0 => '647 W 27th Street',
        ),
    'zip' => '10001',
    'city' => 'New York',
    'state' => 'NY',
    'country_code' => 'US',
    'contact_name' => 'YSDS',
    'contact_phone' => '8777668080',
    'contact_email' => '',
]);

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$then = $now->add(new DateInterval('PT6H'));
$p = new PickupRequest('N', $requestor, $address, $now, $then, [
    Parcel::make(10.0, 10.0, 10.0, 1.0, Unit::INCH, Unit::POUND),
]);

/* @var \Vinnia\Shipping\Pickup $result */
$result = $s->createPickup($p)->wait();

echo var_export($result, true) . PHP_EOL;
