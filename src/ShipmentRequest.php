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
     * @var string
     */
    public $reference = '';

    /**
     * ShipmentRequest constructor.
     * @param string $service
     * @param Address $sender
     * @param Address $recipient
     * @param Parcel $package
     */
    function __construct(string $service, Address $sender, Address $recipient, Parcel $package)
    {
        $this->service = $service;

        parent::__construct($sender, $recipient, $package);
    }

}
