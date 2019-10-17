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
     * @var string|null
     */
    public $labelFormat;

    /**
     * @var string|null
     */
    public $labelSize;

    /**
     * @var bool
     */
    public $signatureRequired = false;

    /**
     * @var string
     */
    public $itn;

    /**
     * ShipmentRequest constructor.
     * @param string $service
     * @param Address $sender
     * @param Address $recipient
     * @param Parcel[] $parcels
     */
    function __construct(string $service, Address $sender, Address $recipient, array $parcels)
    {
        $this->service = $service;

        parent::__construct($sender, $recipient, $parcels);
    }

}
