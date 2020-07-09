<?php declare(strict_types=1);

namespace Vinnia\Shipping;

/**
 * Class CancelPickupRequest
 * @package Vinnia\Shipping
 */
class CancelPickupRequest
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $service;

    /**
     * @var Address
     */
    public $requestorAddress;

    /**
     * @var Address
     */
    public $pickupAddress;

    /**
     * @var \DateTimeImmutable
     */
    public $date;

    /**
     * @var string
     */
    public $locationCode;


    /**
     * CancelPickupRequest constructor.
     * @param string $id
     * @param string $service
     * @param Address $requestorAddress
     * @param Address $pickupAddress
     * @param \DateTimeImmutable $date
     * @param string $locationCode
     */
    public function __construct(
        string $id,
        string $service,
        Address $requestorAddress,
        Address $pickupAddress,
        \DateTimeImmutable $date,
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
