<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\CompositeTracker;
use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\TrackingResult;

class CompositeTrackerTest extends TestCase
{
    public function testReturnsFirstSuccessfulTracking()
    {
        $service = new MockTrackerService([
            new TrackingResult(TrackingResult::STATUS_ERROR, "", ""),
        ]);

        $service2 = new MockTrackerService([
            new TrackingResult(TrackingResult::STATUS_ERROR, "", ""),
            new TrackingResult(TrackingResult::STATUS_SUCCESS, "", ""),
        ]);

        $tracker = new CompositeTracker([$service, $service2]);
        $parsedResult = $tracker->getTrackingStatus("")->wait();
        $this->assertSame(TrackingResult::STATUS_SUCCESS, $parsedResult->status);
    }
}
