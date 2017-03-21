<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-07
 * Time: 18:06
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use DateTimeInterface;
use JsonSerializable;

class TrackingActivity implements JsonSerializable
{

    const STATUS_DELIVERED      = 100;
    const STATUS_IN_TRANSIT     = 200;
    const STATUS_EXCEPTION      = 500;

    /**
     * @var int
     */
    private $status;

    /**
     * @var string
     */
    private $description;

    /**
     * @var DateTimeInterface
     */
    private $date;

    /**
     * @var Address
     */
    private $address;

    /**
     * TrackingActivity constructor.
     * @param int $status
     * @param string $description
     * @param DateTimeInterface $date
     * @param Address $address
     */
    function __construct(int $status, string $description, DateTimeInterface $date, Address $address)
    {
        $this->status = $status;
        $this->description = $description;
        $this->date = $date;
        $this->address = $address;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return DateTimeInterface
     */
    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->getStatus(),
            'description' => $this->getDescription(),
            'date' => $this->getDate()->format('c'),
            'address' => $this->getAddress(),
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
