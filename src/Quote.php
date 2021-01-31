<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Money\Money;
use JsonSerializable;

class Quote implements JsonSerializable
{
    public string $vendor;
    public string $service;
    public Money $price;

    public function __construct(string $vendor, string $service, Money $price)
    {
        $this->vendor = $vendor;
        $this->service = $service;
        $this->price = $price;
    }

    public function toArray(): array
    {
        return [
            'vendor' => $this->vendor,
            'service' => $this->service,
            'price' => $this->price,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
