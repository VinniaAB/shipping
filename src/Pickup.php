<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use DateTimeInterface;

class Pickup
{
    public string $vendor;
    public string $id;
    public string $service;
    public DateTimeInterface $date;
    public string $locationCode;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    public function __construct(
        string $vendor,
        string $id,
        string $service,
        DateTimeInterface $date,
        string $locationCode = '',
        $raw = null
    ) {
        $this->vendor = $vendor;
        $this->id = $id;
        $this->service = $service;
        $this->date = $date;
        $this->locationCode = $locationCode;
        $this->raw = $raw;
    }
}
