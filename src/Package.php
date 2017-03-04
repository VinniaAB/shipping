<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:10
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Vinnia\Util\Measurement\Amount;

class Package
{

    /**
     * @var Amount
     */
    private $width;

    /**
     * @var Amount
     */
    private $height;

    /**
     * @var Amount
     */
    private $length;

    /**
     * @var Amount
     */
    private $weight;

    /**
     * Package constructor.
     * @param Amount $width
     * @param Amount $height
     * @param Amount $length
     * @param Amount $weight
     */
    function __construct(Amount $width, Amount $height, Amount $length, Amount $weight)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->weight = $weight;
    }

    /**
     * @return Amount
     */
    public function getWidth(): Amount
    {
        return $this->width;
    }

    /**
     * @return Amount
     */
    public function getHeight(): Amount
    {
        return $this->height;
    }

    /**
     * @return Amount
     */
    public function getLength(): Amount
    {
        return $this->length;
    }

    /**
     * @return Amount
     */
    public function getWeight(): Amount
    {
        return $this->weight;
    }

    public function convertTo(string $lengthUnit, string $weightUnit): self
    {
        return new Package(
            $this->width->convertTo($lengthUnit),
            $this->height->convertTo($lengthUnit),
            $this->length->convertTo($lengthUnit),
            $this->weight->convertTo($weightUnit)
        );
    }

}
