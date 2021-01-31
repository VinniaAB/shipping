<?php declare(strict_types=1);

namespace Vinnia\Shipping;

class Pickup
{
    /**
     * @var string
     */
    public $vendor;

    /**
     * @var int|string
     */
    public $id;

    /**
     * @var string
     */
    public $service;

    /**
     * @var \DateTimeImmutable
     */
    public $date;

    /**
     * @var string
     */
    public $locationCode;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    /**
     * Pickup constructor.
     * @param string $vendor
     * @param string $id
     * @param string $service
     * @param \DateTimeImmutable $date
     * @param string $locationCode
     * @param null $raw
     */
    public function __construct(
        string $vendor,
        string $id,
        string $service,
        \DateTimeImmutable $date,
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
