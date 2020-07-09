<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\FedEx\Credentials as FedExCredentials;
use Vinnia\Shipping\UPS\Credentials as UPSCredentials;
use Vinnia\Shipping\TNT\Credentials as TNTCredentials;

class AbstractTestCase extends TestCase
{
    /**
     * @param string $name
     * @return DHLCredentials|FedExCredentials|UPSCredentials|TNTCredentials|null
     */
    public function getCredentialsOfName(string $name): ?object
    {
        $credentials = getenv('SERVICE_CREDENTIALS') ?: '';
        $decoded = json_decode($credentials, true);
        $path = __DIR__ . '/../credentials.php';

        // prioritize credentials passed as a JSON-encoded env variable.
        $stuff = [
            $decoded ?: [],
            file_exists($path)
                ? require $path
                : [],
        ];

        foreach ($stuff as $credentials) {
            if (!isset($credentials[$name])) {
                continue;
            }
            $item = $credentials[$name];
            $type = $item['type'];
            unset($item['type']);

            // TODO: add support for UPS & TNT here...
            switch ($type) {
                case 'dhl':
                    return new DHLCredentials(
                        $item['site_id'],
                        $item['password'],
                        $item['account_number']
                    );
                case 'fedex':
                    return new FedExCredentials(
                        $item['credential_key'],
                        $item['credential_password'],
                        $item['account_number'],
                        $item['meter_number']
                    );
            }
        }

        return null;
    }
}
