<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use DateTimeInterface;
use JsonSerializable;

class Tracking implements JsonSerializable
{
    public string $vendor;
    public string $service;

    /**
     * Activities are not guaranteed to be sorted chronologically.
     *
     * @var TrackingActivity[]
     */
    public array $activities;

    /**
     * @var Parcel[]
     */
    public array $parcels = [];
    public ?DateTimeInterface $estimatedDeliveryDate = null;

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

    public function toArray(): array
    {
        return [
            'vendor' => $this->vendor,
            'service' => $this->service,
            'activities' => $this->activities,
            'parcels' => $this->parcels,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
