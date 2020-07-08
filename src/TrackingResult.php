<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

class TrackingResult
{
    const STATUS_SUCCESS = 100;
    const STATUS_ERROR = 500;

    /**
     * @var int
     */
    public $status;

    /**
     * @var string
     */
    public $trackingNumber;

    /**
     * @var string
     */
    public $body;

    /**
     * @var Tracking|null
     */
    public $tracking;

    /**
     * TrackingResult constructor.
     * @param int $status
     * @param string $trackingNumber
     * @param string $body
     * @param null|Tracking $tracking
     */
    public function __construct(int $status, string $trackingNumber, string $body, ?Tracking $tracking = null)
    {
        $this->status = $status;
        $this->trackingNumber = $trackingNumber;
        $this->body = $body;
        $this->tracking = $tracking;
    }
}
