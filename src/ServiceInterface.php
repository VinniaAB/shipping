<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use GuzzleHttp\Promise\PromiseInterface;

interface ServiceInterface extends ShipmentServiceInterface
{
    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface;

    /**
     * @param string[] $trackingNumbers
     * @param array $options vendor specific options
     * @return PromiseInterface resolved with an array of \Vinnia\Shipping\TrackingResult
     */
    public function getTrackingStatus(array $trackingNumbers, array $options = []): PromiseInterface;

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getProofOfDelivery(string $trackingNumber): PromiseInterface;

    /**
     * @param PickupRequest $request
     * @return PromiseInterface
     */
    public function createPickup(PickupRequest $request): PromiseInterface;

    /**
     * @param CancelPickupRequest $request
     * @return PromiseInterface
     */
    public function cancelPickup(CancelPickupRequest $request): PromiseInterface;
}
