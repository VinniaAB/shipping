<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

class TrackingResult
{
    const STATUS_SUCCESS = 100;
    const STATUS_ERROR = 500;

    public int $status;
    public string $trackingNumber;
    public string $body;
    public ?Tracking $tracking;

    public function __construct(int $status, string $trackingNumber, string $body, ?Tracking $tracking = null)
    {
        $this->status = $status;
        $this->trackingNumber = $trackingNumber;
        $this->body = $body;
        $this->tracking = $tracking;
    }
}
