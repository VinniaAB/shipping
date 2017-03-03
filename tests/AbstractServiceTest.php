<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 15:38
 */

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;

abstract class AbstractServiceTest extends TestCase
{

    /**
     * @return array
     */
    abstract public function addressAndServiceProvider(): array;

    /**
     * @dataProvider addressAndServiceProvider
     * @param Address $sender
     * @param Address $recipient
     * @param ServiceInterface $service
     * @param bool $expected
     */
    public function testRate(Address $sender, Address $recipient, ServiceInterface $service, bool $expected)
    {
        if (!$expected) {
            $this->expectException(RejectionException::class);
        }

        $package = new Package(30, 30, 30, 5000);
        $promise = $service->getQuotes($sender, $recipient, $package);

        /* @var Quote[] $quotes */
        $quotes = $promise->wait();

        if ($expected) {
            $this->assertNotEmpty($quotes);
            $this->assertInstanceOf(Quote::class, $quotes[0]);

            foreach ($quotes as $quote) {
                echo sprintf('%s %s: %d' . PHP_EOL, $quote->getVendor(), $quote->getProduct(), $quote->getAmount()->getAmount());
            }
        }
    }

}
