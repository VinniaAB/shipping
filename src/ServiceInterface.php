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
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @param array $options
     * @return PromiseInterface
     */
    public function createLabel(Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface;

}
