<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-07
 * Time: 18:06
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;

class Tracking implements JsonSerializable
{

    /**
     * @var string
     */
    public $vendor;

    /**
     * @var string
     */
    public $service;

    /**
     * @var TrackingActivity[]
     */
    public $activities;

    /**
     * Tracking constructor.
     * @param string $vendor
     * @param string $service
     * @param TrackingActivity[] $activities
     */
    function __construct(string $vendor, string $service, array $activities)
    {
        $this->vendor = $vendor;
        $this->service = $service;
        $this->activities = $activities;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'vendor' => $this->vendor,
            'service' => $this->service,
            'activities' => $this->activities,
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
