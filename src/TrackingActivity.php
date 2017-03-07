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

class TrackingActivity
{

    /**
     * @var string
     */
    private $status;

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
     * @param string $status
     * @param DateTimeInterface $date
     * @param Address $address
     */
    function __construct(string $status, DateTimeInterface $date, Address $address)
    {
        $this->status = $status;
        $this->date = $date;
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
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

}
