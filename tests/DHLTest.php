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
        $dhl = new DHL(new Client(), $credentials, DHL::URL_PRODUCTION);
        return [
            'Luleå, Sweden -> Malmö, Sweden' => [
                new Address([], '97334', 'Luleå', '', 'SE'),
                new Address([], '21115', 'Malmö', '', 'SE'),
                $dhl,
                true
            ],
            'Boulder, CO, USA -> Minneapolis, MN, US' => [
                new Address([], '80302', 'Boulder', 'CO', 'US'),
                new Address([], '55417', 'Minneapolis', 'MN', 'US'),
                $dhl,
                true,
            ],
            'Stockholm, Sweden -> Munich, Germany' => [
                new Address([], '10000', 'Stockholm', '', 'SE'),
                new Address([], '80469', 'Munich', '', 'DE'),
                $dhl,
                true
            ],
        ];
    }

}
