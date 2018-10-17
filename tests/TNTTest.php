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
        $credentials = new Credentials(
            $data['tnt']['username'],
            $data['tnt']['password'],
            $data['tnt']['account_number'],
            $data['tnt']['account_country']
        );
        $guzzle = new Client();

        return new Service($guzzle, $credentials, Service::URL_PRODUCTION);
    }

    /**
     * @return string[][]
     */
    public function trackingNumberProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        return [
            [$data['tnt']['tracking_numbers']],
        ];
    }

    public function testGetProofOfDeliveryThrowsError()
    {
        $this->expectException(\Exception::class);
        $this->service->getProofOfDelivery('123');
    }
}
