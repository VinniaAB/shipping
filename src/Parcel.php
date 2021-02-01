<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Length;
use Vinnia\Util\Measurement\LengthUnit;
use Vinnia\Util\Measurement\Mass;
use Vinnia\Util\Measurement\MassUnit;
use Vinnia\Util\Measurement\Meter;

class Parcel implements JsonSerializable
{
    public Length $width;
    public Length $height;
    public Length $length;
    public Mass $weight;

    public function __construct(Length $width, Length $height, Length $length, Mass $weight)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->weight = $weight;
    }

    public function convertTo(LengthUnit $lengthUnit, MassUnit $weightUnit): self
    {
        return new Parcel(
            $this->width->convertTo($lengthUnit),
            $this->height->convertTo($lengthUnit),
            $this->length->convertTo($lengthUnit),
            $this->weight->convertTo($weightUnit)
        );
    }

    public function getVolume(LengthUnit $unit): float
    {
        $amounts = [$this->width, $this->height, $this->length];
        return array_reduce($amounts, function (float $carry, Length $current) use ($unit): float {
            $value = $current
                ->convertTo($unit)
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
        ?LengthUnit $lengthUnit = null,
        ?MassUnit $weightUnit = null
    ): Parcel {
        $lengthUnit = $lengthUnit ?? Meter::unit();
        $weightUnit = $weightUnit ?? Kilogram::unit();

        return new Parcel(
            new Length($width, $lengthUnit),
            new Length($height, $lengthUnit),
            new Length($length, $lengthUnit),
            new Mass($weight, $weightUnit)
        );
    }

    /**
     * @param Parcel[] $parcels
     * @param MassUnit $unit
     * @return Mass
     */
    public static function getTotalWeight(array $parcels, MassUnit $unit): Mass
    {
        return array_reduce(
            $parcels,
            fn ($carry, $parcel) => $carry->add($parcel->weight->convertTo($unit)),
            new Mass(0.0, $unit)
        );
    }
}
