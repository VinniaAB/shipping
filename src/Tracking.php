<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-07
 * Time: 18:06
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

class Tracking
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
     * @var TrackingActivity[]
     */
    private $activities;

    /**
     * Tracking constructor.
     * @param string $vendor
     * @param string $product
     * @param TrackingActivity[] $activities
     */
    function __construct(string $vendor, string $product, array $activities)
    {
        $this->vendor = $vendor;
        $this->product = $product;
        $this->activities = $activities;
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
     * @return TrackingActivity[]
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

}
