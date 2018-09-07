<?php
/**
 * Created by PhpStorm.
 * User: Bro
 * Date: 04.09.2018
 * Time: 13:56
 */

namespace Vinnia\Shipping;


class Pickup
{

    /**
     * @var int|string
     */
    public $id;

    /**
     * @var string
     */
    public $vendor;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    /**
     * Pickup constructor.
     * @param string $id
     * @param string $vendor
     * @param null $raw
     */
    function __construct(string $id, string $vendor, $raw = null)
    {
        $this->id = $id;
        $this->vendor = $vendor;
        $this->raw = $raw;
    }

}
