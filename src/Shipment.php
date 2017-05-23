<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-04-04
 * Time: 15:00
 */

namespace Vinnia\Shipping;


class Shipment
{

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $vendor;

    /**
     * Binary pdf data
     * @var string
     */
    public $labelData;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    /**
     * Label constructor.
     * @param string $id
     * @param string $vendor
     * @param string $labelData
     * @param mixed $raw
     */
    function __construct(string $id, string $vendor, string $labelData, $raw = null)
    {
        $this->id = $id;
        $this->vendor = $vendor;
        $this->labelData = $labelData;
        $this->raw = $raw;
    }

}
