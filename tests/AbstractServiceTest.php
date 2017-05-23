<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 15:38
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\Tracking;
use Vinnia\Shipping\TrackingActivity;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

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

    /**
     * @return string[][]
     */
    abstract public function trackingNumberProvider(): array;

    public function setUp()
    {
        parent::setUp();

        $this->service = $this->getService();
    }

    public function addressProvider(): array
    {
        return [
            'Luleå, Sweden -> Malmö, Sweden' => [
                new Address('Company AB', [], '97334', 'Luleå', '', 'SE'),
                new Address('Company AB', [], '21115', 'Malmö', '', 'SE'),
            ],
            'Boulder, CO, USA -> Minneapolis, MN, US' => [
                new Address('Company AB', [], '80302', 'Boulder', 'CO', 'US'),
                new Address('Company AB', [], '55417', 'Minneapolis', 'MN', 'US'),
            ],
            'Stockholm, Sweden -> Munich, Germany' => [
                new Address('Company AB', [], '11157', 'Stockholm', '', 'SE'),
                new Address('Company AB', [], '80469', 'Munich', '', 'DE'),
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
        $size = new Amount(30, Unit::CENTIMETER);
        $package = new Package($size, $size, $size, new Amount(5, Unit::KILOGRAM));
        $promise = $this->service->getQuotes($sender, $recipient, $package);

        try {
            /* @var Quote[] $quotes */
            $quotes = $promise->wait();
        }
        catch (RejectionException $e) {
            $reason = $e->getReason();

            var_dump($reason);
        }

        $this->assertTrue(is_array($quotes));

        echo sprintf(
            '%s %s, %s to %s %s, %s' . PHP_EOL,
            $sender->zip,
            $sender->city,
            $sender->countryCode,
            $recipient->zip,
            $recipient->city,
            $recipient->countryCode
        );

        foreach ($quotes as $quote) {
            $this->assertInstanceOf(Quote::class, $quote);

            echo sprintf(
                '%s %s: %.2f %s' . PHP_EOL,
                $quote->vendor,
                $quote->service,
                $quote->price->getAmount() / 100,
                $quote->price->getCurrency()
            );
        }
    }

    /**
     * @dataProvider trackingNumberProvider
     * @param string $trackingNumber
     */
    public function testGetTrackingStatus(string $trackingNumber)
    {
        $promise = $this->service->getTrackingStatus($trackingNumber);

        /* @var Tracking|null $tracking */
        $tracking = $promise->wait();

        $this->assertInstanceOf(Tracking::class, $tracking);
        $this->assertNotEmpty($tracking->activities);

        echo sprintf('%s %s' . PHP_EOL, $tracking->vendor, $tracking->service);

        foreach ($tracking->activities as $activity) {
            $this->assertInstanceOf(TrackingActivity::class, $activity);

            echo sprintf(
                '%s: %d %s %s' . PHP_EOL,
                $activity->date->format('c'),
                $activity->status,
                $activity->description,
                $activity->address->city
            );
        }

        $prev = PHP_INT_MAX;
        foreach ($tracking->activities as $activity) {
            $ts = $activity->date->getTimestamp();
            $this->assertLessThanOrEqual($prev, $ts);
            $prev = $ts;
        }
    }

}
