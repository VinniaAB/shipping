<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Money\Money;
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
     * @var Money
     */
    public $price;

    /**
     * Quote constructor.
     * @param string $vendor
     * @param string $service
     * @param Money $price
     */
    public function __construct(string $vendor, string $service, Money $price)
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
