<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-04-04
 * Time: 15:00
 */

namespace Vinnia\Shipping;


class Label
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
     * Label data format
     * @var string
     */
    public $format;

    /**
     * Binary label data
     * @var string
     */
    public $data;

    /**
     * Label constructor.
     * @param string $id
     * @param string $vendor
     * @param string $format
     * @param string $data
     */
    function __construct(string $id, string $vendor, string $format, string $data)
    {
        $this->id = $id;
        $this->vendor = $vendor;
        $this->format = $format;
        $this->data = $data;
    }

}
