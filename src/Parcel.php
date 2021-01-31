<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Meter;
use Vinnia\Util\Measurement\Unit;

class Parcel implements JsonSerializable
{
    public Amount $width;
    public Amount $height;
    public Amount $length;
    public Amount $weight;

    /**
     * Package constructor.
     * @param Amount $width
     * @param Amount $height
     * @param Amount $length
     * @param Amount $weight
     */
    public function __construct(Amount $width, Amount $height, Amount $length, Amount $weight)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->weight = $weight;
    }

    public function convertTo(Unit $lengthUnit, Unit $weightUnit): self
    {
        return new Parcel(
            $this->width->convertTo($lengthUnit),
            $this->height->convertTo($lengthUnit),
            $this->length->convertTo($lengthUnit),
            $this->weight->convertTo($weightUnit)
        );
    }

    public function getVolume(Unit $lengthUnit): float
    {
        $amounts = [$this->width, $this->height, $this->length];
        return array_reduce($amounts, function (float $carry, Amount $current) use ($lengthUnit): float {
            $value = $current
                ->convertTo($lengthUnit)
                ->getValue();
            return $carry * $value;
        }, 1.0);
    }

    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'weight' => $this->weight,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function make(
        float $width,
        float $height,
        float $length,
        float $weight,
        ?Unit $lengthUnit = null,
        ?Unit $weightUnit = null
    ): Parcel {
        $lengthUnit = $lengthUnit ?? Meter::unit();
        $weightUnit = $weightUnit ?? Kilogram::unit();

        return new Parcel(
            new Amount($width, $lengthUnit),
            new Amount($height, $lengthUnit),
            new Amount($length, $lengthUnit),
            new Amount($weight, $weightUnit)
        );
    }

    /**
     * @param Parcel[] $parcels
     * @param Unit $unit
     * @return Amount
     */
    public static function getTotalWeight(array $parcels, Unit $unit): Amount
    {
        return array_reduce(
            $parcels,
            fn ($carry, $parcel) => $carry->add($parcel->weight->convertTo($unit)),
            new Amount(0, $unit)
        );
    }
}
