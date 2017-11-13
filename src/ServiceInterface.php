<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:06
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use GuzzleHttp\Promise\PromiseInterface;

interface ServiceInterface
{

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @param array $options vendor specific options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface;

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface resolved with an array of \Vinnia\Shipping\Shipment
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface;

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface;

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface;

}
