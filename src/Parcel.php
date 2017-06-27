<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:10
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use Vinnia\Util\Collection;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

use JsonSerializable;
use LogicException;

class Parcel implements JsonSerializable
{

    /**
     * @var Amount
     */
    public $width;

    /**
     * @var Amount
     */
    public $height;

    /**
     * @var Amount
     */
    public $length;

    /**
     * @var Amount
     */
    public $weight;

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

    public function convertTo(string $lengthUnit, string $weightUnit): self
    {
        return new Parcel(
            $this->width->convertTo($lengthUnit),
            $this->height->convertTo($lengthUnit),
            $this->length->convertTo($lengthUnit),
            $this->weight->convertTo($weightUnit)
        );
    }

    /**
     * @param string $lengthUnit
     * @return float
     */
    public function getVolume(string $lengthUnit = Unit::METER): float
    {
        $amounts = [$this->width, $this->height, $this->length];
        return array_reduce($amounts, function (float $carry, Amount $current) use ($lengthUnit): float {
            $value = $current
                ->convertTo($lengthUnit)
                ->getValue();
            return $carry * $value;
        }, 1.0);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'weight' => $this->weight,
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param float $width
     * @param float $height
     * @param float $length
     * @param float $weight
     * @param string $lengthUnit
     * @param string $weightUnit
     * @return Parcel
     */
    public static function make(
        float $width,
        float $height,
        float $length,
        float $weight,
        string $lengthUnit = Unit::METER,
        string $weightUnit = Unit::KILOGRAM
    ): Parcel
    {
        return new Parcel(
            new Amount($width, $lengthUnit),
            new Amount($height, $lengthUnit),
            new Amount($length, $lengthUnit),
            new Amount($weight, $weightUnit)
        );
    }

}
