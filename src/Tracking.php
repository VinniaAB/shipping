<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use DateTimeInterface;
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
     * Activities are not guaranteed to be sorted chronologically.
     *
     * @var TrackingActivity[]
     */
    public $activities;

    /**
     * @var Parcel[]
     */
    public $parcels = [];

    /**
     * @var DateTimeInterface|null
     */
    public $estimatedDeliveryDate;

    /**
     * Tracking constructor.
     * @param string $vendor
     * @param string $service
     * @param TrackingActivity[] $activities
     */
    public function __construct(string $vendor, string $service, array $activities)
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
            'parcels' => $this->parcels,
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
