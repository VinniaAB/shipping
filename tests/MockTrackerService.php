<?php

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Vinnia\Shipping\CancelPickupRequest;
use Vinnia\Shipping\PickupRequest;
use Vinnia\Shipping\QuoteRequest;
use Vinnia\Shipping\ServiceInterface;
use Vinnia\Shipping\ShipmentRequest;

class MockTrackerService implements ServiceInterface
{

    private array $trackingResults;

    public function __construct(array $trackingResults)
    {
        $this->trackingResults = $trackingResults;
    }

    public function getQuotes(QuoteRequest $request): PromiseInterface
    {
        // TODO: Implement getQuotes() method.
    }

    public function getTrackingStatus(array $trackingNumbers, array $options = []): PromiseInterface
    {
        return Create::promiseFor($this->trackingResults);
    }

    public function getAvailableServices(QuoteRequest $request): PromiseInterface
    {
        // TODO: Implement getAvailableServices() method.
    }

    public function getProofOfDelivery(string $trackingNumber): PromiseInterface
    {
        // TODO: Implement getProofOfDelivery() method.
    }

    public function createPickup(PickupRequest $request): PromiseInterface
    {
        // TODO: Implement createPickup() method.
    }

    public function cancelPickup(CancelPickupRequest $request): PromiseInterface
    {
        // TODO: Implement cancelPickup() method.
    }

    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        // TODO: Implement createShipment() method.
    }

    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        // TODO: Implement cancelShipment() method.
    }
}
