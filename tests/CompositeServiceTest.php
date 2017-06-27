<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-04
 * Time: 23:33
 */

namespace Vinnia\Shipping\Tests;


use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\CompositeService;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Quote;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tracking;
use Vinnia\Util\Measurement\Amount;

class CompositeServiceTest extends TestCase
{

    public function testFlattensQuoteResponses()
    {
        $a = new class implements ServiceInterface {
            public function getQuotes(QuoteRequest $request): PromiseInterface
            {
                return \GuzzleHttp\Promise\promise_for([new Quote('DHL', '', new Money(0, new Currency('USD')))]);
            }
            public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
            {
            }
            public function createShipment(ShipmentRequest $request): PromiseInterface
            {
            }
            public function cancelShipment(string $id, array $data = []): PromiseInterface
            {
            }
        };
        $b = new class implements ServiceInterface {
            public function getQuotes(Address $sender, Address $recipient, Parcel $package, array $options = []): PromiseInterface
            {
                return \GuzzleHttp\Promise\rejection_for(false);
            }
            public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
            {
            }
            public function createShipment(ShipmentRequest $request): PromiseInterface
            {
            }
            public function cancelShipment(string $id, array $data = []): PromiseInterface
            {
            }
        };
        $c = new class implements ServiceInterface {
            public function getQuotes(Address $sender, Address $recipient, Parcel $package, array $options = []): PromiseInterface
            {
                return \GuzzleHttp\Promise\promise_for([new Quote('UPS', '', new Money(0, new Currency('USD')))]);
            }
            public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
            {
            }
            public function createShipment(ShipmentRequest $request): PromiseInterface
            {
            }
            public function cancelShipment(string $id, array $data = []): PromiseInterface
            {
            }
        };
        $service = new CompositeService([$a, $b, $c]);
        $address = new Address('', [], '', '', '', '');
        $size = new Amount(1, 'cm');
        $package = new Parcel($size, $size, $size, new Amount(1, 'kg'));
        $promise = $service->getQuotes();

        /* @var Quote[] $quotes */
        $quotes = $promise->wait();

        $this->assertCount(2, $quotes);
        $this->assertEquals('DHL', $quotes[0]->vendor);
        $this->assertEquals('UPS', $quotes[1]->vendor);
    }

    public function testReturnsFirstSuccessfulTracking()
    {
        $a = new class implements ServiceInterface {
            public function getQuotes(Address $sender, Address $recipient, Parcel $package, array $options = []): PromiseInterface
            {
            }
            public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
            {
                return \GuzzleHttp\Promise\promise_for(new Tracking('DHL', '', []));
            }
            public function createShipment(ShipmentRequest $request): PromiseInterface
            {
            }
            public function cancelShipment(string $id, array $data = []): PromiseInterface
            {
            }
        };
        $b = new class implements ServiceInterface {
            public function getQuotes(Address $sender, Address $recipient, Parcel $package, array $options = []): PromiseInterface
            {
            }
            public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
            {
                return \GuzzleHttp\Promise\promise_for(new Tracking('DHL', '', []));
            }
            public function createShipment(ShipmentRequest $request): PromiseInterface
            {
            }
            public function cancelShipment(string $id, array $data = []): PromiseInterface
            {
            }
        };

        $service = new CompositeService([$a, $b]);
        $tracking = $service->getTrackingStatus('123')->wait();

        $this->assertInstanceOf(Tracking::class, $tracking);
        $this->assertEquals('DHL', $tracking->vendor);
    }

    public function testWithOnlyFiltersCorrectlyWithSpecificClass()
    {
        $guzzle = new Client();
        $service = new CompositeService([
            new \Vinnia\Shipping\DHL\Service($guzzle, new \Vinnia\Shipping\DHL\Credentials('','','')),
            new \Vinnia\Shipping\UPS\Service($guzzle, new \Vinnia\Shipping\UPS\Credentials('','','')),
        ]);

        $filtered = $service->withOnly([
            \Vinnia\Shipping\DHL\Service::class,
        ]);

        $this->assertCount(1, $filtered->getDelegates());
        $this->assertInstanceOf(\Vinnia\Shipping\DHL\Service::class, $filtered->getDelegates()[0]);
    }

    public function testWithOnlyFiltersCorrectlyWithParentInterface()
    {
        $guzzle = new Client();
        $service = new CompositeService([
            new \Vinnia\Shipping\DHL\Service($guzzle, new \Vinnia\Shipping\DHL\Credentials('','','')),
            new \Vinnia\Shipping\UPS\Service($guzzle, new \Vinnia\Shipping\UPS\Credentials('','','')),
        ]);

        $filtered = $service->withOnly([
            \Vinnia\Shipping\ServiceInterface::class,
        ]);

        $this->assertCount(2, $filtered->getDelegates());
        $this->assertInstanceOf(\Vinnia\Shipping\DHL\Service::class, $filtered->getDelegates()[0]);
        $this->assertInstanceOf(\Vinnia\Shipping\UPS\Service::class, $filtered->getDelegates()[1]);
    }

}
