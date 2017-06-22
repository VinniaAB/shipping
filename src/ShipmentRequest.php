<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-23
 * Time: 14:23
 */

namespace Vinnia\Shipping;

class ShipmentRequest extends QuoteRequest
{

    /**
     * @var string
     */
    public $service;

    /**
     * ShipmentRequest constructor.
     * @param string $service
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     */
    function __construct(string $service, Address $sender, Address $recipient, Package $package)
    {
        $this->service = $service;

        parent::__construct($sender, $recipient, $package);
    }

}
