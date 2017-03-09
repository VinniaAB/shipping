<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-09
 * Time: 12:07
 */

namespace Vinnia\Shipping\Tests;


use GuzzleHttp\Client;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\TNT\Credentials;
use Vinnia\Shipping\TNT\Service;

class TNTTest extends AbstractServiceTest
{

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new Credentials($data['tnt']['username'], $data['tnt']['password']);
        $guzzle = new Client();

        return new Service($guzzle, $credentials, Service::URL_PRODUCTION);
    }

    /**
     * @return string[][]
     */
    public function trackingNumberProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        return array_map(function (string $value) {
            return [$value];
        }, $data['tnt']['tracking_numbers']);
    }

}
