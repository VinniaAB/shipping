<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 19:24
 */

namespace Vinnia\Shipping\FedEx;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Shipping\ServiceInterface;

class Service implements ServiceInterface
{

    const URL_TEST = 'https://test';
    const URL_PRODUCTION = 'https://prod';

    /**
     * @var ClientInterface
     */
    private $guzzle;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var string
     */
    private $url;

    function __construct(ClientInterface $guzzle, Credentials $credentials, string $url = self::URL_PRODUCTION)
    {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->url = $url;
    }

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        // TODO: Implement getQuotes() method.
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        // TODO: Implement getTrackingStatus() method.
    }
}