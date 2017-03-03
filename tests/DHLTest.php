<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 17:17
 */

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use Vinnia\Shipping\DHL\Service as DHL;
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\Address;

class DHLTest extends AbstractServiceTest
{

    /**
     * @return array
     */
    public function addressAndServiceProvider(): array
    {
        $data = require __DIR__ . '/../credentials.php';
        $credentials = new DHLCredentials(
            $data['dhl']['site_id'],
            $data['dhl']['password'],
            $data['dhl']['account_number']
        );
        $dhl = new DHL(new Client(), $credentials, DHL::URL_TEST);
        $sender = new Address(['Delfingatan 4'], '97334', 'Luleå', '', 'SE');
        $recipient = new Address(['Lilla Varvsgatan 14'], '21115', 'Malmö', '', 'SE');
        return [
            [$sender, $recipient, $dhl, true],
        ];
    }

}
