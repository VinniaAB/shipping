<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-03
 * Time: 17:22
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Money\Money;
use JsonSerializable;

class Quote implements JsonSerializable
{

    /**
     * @var string
     */
    private $vendor;

    /**
     * @var string
     */
    private $product;

    /**
     * @var Money
     */
    private $amount;

    /**
     * Quote constructor.
     * @param string $vendor
     * @param string $product
     * @param Money $amount
     */
    function __construct(string $vendor, string $product, Money $amount)
    {
        $this->vendor = $vendor;
        $this->product = $product;
        $this->amount = $amount;
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
    public function getProduct(): string
    {
        return $this->product;
    }

    /**
     * @return Money
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'vendor' => $this->getVendor(),
            'product' => $this->getProduct(),
            'amount' => $this->getAmount(),
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
