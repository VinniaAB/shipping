<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\UPS\Service as UPS;
use Vinnia\Shipping\UPS\Credentials as UPSCredentials;

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

    /**
     * @return string[][]
     */
    public function trackingNumberProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';

        return [
            [$data['ups']['tracking_numbers']],
        ];
    }
}
