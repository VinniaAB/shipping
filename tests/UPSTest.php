<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 16:44
 */

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use Vinnia\Shipping\UPS\Service as UPS;
use Vinnia\Shipping\UPS\Credentials as UPSCredentials;
use Vinnia\Shipping\Address;

class UPSTest extends AbstractServiceTest
{

    /**
     * @return array
     */
    public function addressAndServiceProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new UPSCredentials(
            $data['ups']['username'],
            $data['ups']['password'],
            $data['ups']['access_license']
        );

        $client = new Client();
        $sender = new Address(['Delfingatan 4'], '97334', 'Luleå', '', 'SE');
        $recipient = new Address(['Lilla Varvsgatan 14'], '21115', 'Malmö', '', 'SE');
        return [
            'UPS Worldwide Express' => [
                $sender,
                $recipient,
                new UPS($client, $credentials, '07', UPS::URL_PRODUCTION),
                true,
            ],
            'UPS Worldwide Express Plus' => [
                $sender,
                $recipient,
                new UPS($client, $credentials, '54', UPS::URL_PRODUCTION),
                true,
            ],
            // shouldn't work for the specified addresses
            'UPS Standard' => [
                $sender,
                $recipient,
                new UPS($client, $credentials, '11', UPS::URL_PRODUCTION),
                false,
            ],
        ];
    }

}
