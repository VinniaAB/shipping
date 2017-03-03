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
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface;

}
