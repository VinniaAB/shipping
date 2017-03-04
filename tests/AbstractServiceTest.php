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
     * @var ServiceInterface
     */
    public $service;

    /**
     * @return ServiceInterface
     */
    abstract public function getService(): ServiceInterface;

    public function setUp()
    {
        parent::setUp();

        $this->service = $this->getService();
    }

    public function addressProvider(): array
    {
        return [
            'Luleå, Sweden -> Malmö, Sweden' => [
                new Address([], '97334', 'Luleå', '', 'SE'),
                new Address([], '21115', 'Malmö', '', 'SE'),
            ],
            'Boulder, CO, USA -> Minneapolis, MN, US' => [
                new Address([], '80302', 'Boulder', 'CO', 'US'),
                new Address([], '55417', 'Minneapolis', 'MN', 'US'),
            ],
            'Stockholm, Sweden -> Munich, Germany' => [
                new Address([], '10000', 'Stockholm', '', 'SE'),
                new Address([], '80469', 'Munich', '', 'DE'),
            ],
        ];
    }

    /**
     * @dataProvider addressProvider
     * @param Address $sender
     * @param Address $recipient
     */
    public function testGetQuotes(Address $sender, Address $recipient)
    {
        $package = new Package(30, 30, 30, 5000);
        $promise = $this->service->getQuotes($sender, $recipient, $package);

        /* @var Quote[] $quotes */
        $quotes = $promise->wait();

        $this->assertTrue(is_array($quotes));

        foreach ($quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);

            echo sprintf(
                '%s %s: %.2f %s' . PHP_EOL,
                $quote->getVendor(),
                $quote->getProduct(),
                $quote->getAmount()->getAmount() / 100,
                $quote->getAmount()->getCurrency()
            );
        }
    }

}
