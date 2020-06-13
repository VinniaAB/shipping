<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 17:22
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;

class Quote implements JsonSerializable
{

    /**
     * @var string
     */
    public $vendor;

    /**
     * @var string
     */
    public $service;

    /**
     * @var Array
     */
    public $price;

    /**
     * Quote constructor.
     * @param string $vendor
     * @param string $service
     * @param Array $price
     */
    function __construct(string $vendor, string $service, Array $price)
    {
        $this->vendor = $vendor;
        $this->service = $service;
        $this->price = $price;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'vendor' => $this->vendor,
            'service' => $this->service,
            'price' => $this->price,
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

}
