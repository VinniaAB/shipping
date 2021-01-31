<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use DateTimeInterface;

class CancelPickupRequest
{
    public string $id;
    public string $service;
    public Address $requestorAddress;
    public Address $pickupAddress;
    public DateTimeInterface $date;
    public string $locationCode;

    public function __construct(
        string $id,
        string $service,
        Address $requestorAddress,
        Address $pickupAddress,
        DateTimeInterface $date,
        string $locationCode = ''
    ) {
        $this->id = $id;
        $this->service = $service;
        $this->requestorAddress = $requestorAddress;
        $this->pickupAddress = $pickupAddress;
        $this->date = $date;
        $this->locationCode = $locationCode;
    }
}
