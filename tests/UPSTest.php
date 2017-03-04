<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 16:44
 */

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\UPS\Service as UPS;
use Vinnia\Shipping\UPS\Credentials as UPSCredentials;
use Vinnia\Shipping\Address;

class UPSTest extends AbstractServiceTest
{

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new UPSCredentials(
            $data['ups']['username'],
            $data['ups']['password'],
            $data['ups']['access_license']
        );
        return new UPS(new Client(), $credentials, UPS::URL_PRODUCTION);
    }

}
