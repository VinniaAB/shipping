<?php
declare(strict_types=1);

namespace Vinnia\Shipping;

use GuzzleHttp\Promise\PromiseInterface;

interface ShipmentServiceInterface
{
    /**
     * @param ShipmentRequest $request
     * @see \Vinnia\Shipping\Shipment
     * @return PromiseInterface resolved with an array of \Vinnia\Shipping\Shipment
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface;

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface;
}
