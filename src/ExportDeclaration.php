<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-08-25
 * Time: 12:45
 */
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
     * @var Amount
     */
    public $weight;

    /**
     * ExportDeclaration constructor.
     * @param string $description
     * @param string $originCountryCode
     * @param int $quantity
     * @param float $value
     * @param Amount $weight
     */
    public function __construct(string $description, string $originCountryCode, int $quantity, float $value, Amount $weight)
    {
        $this->description = $description;
        $this->originCountryCode = $originCountryCode;
        $this->quantity = $quantity;
        $this->value = $value;
        $this->weight = $weight;
    }

}
