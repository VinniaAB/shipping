<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use DateTimeInterface;
use JsonSerializable;

final class TrackingActivity implements JsonSerializable
{
    const STATUS_DELIVERED = 100;
    const STATUS_IN_TRANSIT = 200;
    const STATUS_EXCEPTION = 500;
    const STATUS_NOTIFICATION = 700;

    public int $status;
    public string $description;
    public DateTimeInterface $date;
    public Address $address;
    public string $originalDate;

    public function __construct(
        int $status,
        string $description,
        DateTimeInterface $date,
        Address $address,
        string $originalDate = ''
    ) {
        $this->status = $status;
        $this->description = $description;
        $this->date = $date;
        $this->address = $address;
        $this->originalDate = $originalDate ?: $date->format('c');
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'description' => $this->description,
            'date' => $this->date->format('c'),
            'address' => $this->address,
            'original_date' => $this->originalDate,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
