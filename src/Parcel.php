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

use JsonSerializable;
use Vinnia\Util\Measurement\Unit;

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

}
