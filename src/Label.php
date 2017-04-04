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

    const FORMAT_PDF = 'pdf';

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $vendor;

    /**
     * Label data format
     * @var string
     */
    private $format;

    /**
     * Binary label data
     * @var string
     */
    private $data;

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

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getVendor(): string
    {
        return $this->vendor;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

}
