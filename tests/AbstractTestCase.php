<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\FedEx\Credentials as FedExCredentials;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\UPS\Credentials as UPSCredentials;
use Vinnia\Shipping\TNT\Credentials as TNTCredentials;

class AbstractTestCase extends TestCase
{
    protected static bool $didCleanupOutput = false;

    public function setUp(): void
    {
        parent::setUp();

        if (!static::$didCleanupOutput) {
            $files = glob(__DIR__ . '/output/*.{pdf,txt}', GLOB_BRACE);
            foreach ($files as $file) {
                unlink($file);
            }
            static::$didCleanupOutput = true;
        }
    }

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

    /**
     * @param callable $fn
     * @param mixed ...$args
     */
    public function executeCreateShipmentTest(callable $fn, ...$args): void
    {
        /* @var Shipment[] $result */
        $result = call_user_func($fn, ...$args);

        $this->assertNotCount(0, $result);

        foreach ($result as $shipment) {
            file_put_contents(
                sprintf('%s/%s.pdf', __DIR__ . '/output', $shipment->id),
                $shipment->labelData
            );
            file_put_contents(
                sprintf('%s/%s.txt', __DIR__ . '/output', $shipment->id),
                $shipment->raw
            );
        }
    }
}
