<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Vinnia\Util\Measurement\Amount;

class ExportDeclaration
{
    public string $description;
    public string $originCountryCode;
    public int $quantity;
    public float $value;
    public string $currency;
    public Amount $weight;

    public function __construct(
        string $description,
        string $originCountryCode,
        int $quantity,
        float $value,
        string $currency,
        Amount $weight
    ) {
        $this->description = $description;
        $this->originCountryCode = $originCountryCode;
        $this->quantity = $quantity;
        $this->value = $value;
        $this->currency = $currency;
        $this->weight = $weight;
    }
}
