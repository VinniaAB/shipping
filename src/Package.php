<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:10
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

class Package
{

    /**
     * Width in cm
     * @var int
     */
    private $width;

    /**
     * Height in cm
     * @var int
     */
    private $height;

    /**
     * Length in cm
     * @var int
     */
    private $length;

    /**
     * Weight in grams
     * @var int
     */
    private $weight;

    /**
     * Package constructor.
     * @param int $width
     * @param int $height
     * @param int $length
     * @param int $weight
     */
    function __construct(int $width, int $height, int $length, int $weight)
    {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->weight = $weight;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @return int
     */
    public function getVolume(): int
    {
        return $this->getWidth() * $this->getHeight() * $this->getLength();
    }

}
