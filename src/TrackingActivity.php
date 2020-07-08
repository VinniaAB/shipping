<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use DateTimeInterface;
use JsonSerializable;

class TrackingActivity implements JsonSerializable
{
    const STATUS_DELIVERED = 100;
    const STATUS_IN_TRANSIT = 200;
    const STATUS_EXCEPTION = 500;
    const STATUS_NOTIFICATION = 700;

    /**
     * @var int
     */
    public $status;

    /**
     * @var string
     */
    public $description;

    /**
     * @var DateTimeInterface
     */
    public $date;

    /**
     * @var Address
     */
    public $address;

    /**
     * TrackingActivity constructor.
     * @param int $status
     * @param string $description
     * @param DateTimeInterface $date
     * @param Address $address
     */
    public function __construct(int $status, string $description, DateTimeInterface $date, Address $address)
    {
        $this->status = $status;
        $this->description = $description;
        $this->date = $date;
        $this->address = $address;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'description' => $this->description,
            'date' => $this->date->format('c'),
            'address' => $this->address,
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
