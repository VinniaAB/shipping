<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Vinnia\Util\Measurement\Amount;

/**
 * Class ExportDeclaration
 * @package Vinnia\Shipping
 */
class ExportDeclaration
{
    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $originCountryCode;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var float
     */
    public $value;

    /**
     * @var string
     */
    public $currency;

    /**
     * @var Amount
     */
    public $weight;

    /**
     * ExportDeclaration constructor.
     * @param string $description
     * @param string $originCountryCode
     * @param int $quantity
     * @param float $value
     * @param string $currency
     * @param Amount $weight
     */
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
