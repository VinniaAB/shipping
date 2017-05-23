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
use DateTimeInterface;

interface ServiceInterface
{

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @param array $options vendor specific options
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @param array $options vendor specific options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface;

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface;

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface;

}
