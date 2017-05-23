<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-23
 * Time: 14:23
 */

namespace Vinnia\Shipping;

use DateTimeInterface;
use DateTimeImmutable;

class ShipmentRequest
{

    /**
     * @var string
     */
    public $service;

    /**
     * @var Address
     */
    public $sender;

    /**
     * @var Address
     */
    public $recipient;

    /**
     * @var Package
     */
    public $package;

    /**
     * @var DateTimeInterface
     */
    public $date;

    /**
     * @var float
     */
    public $value = 0.0;

    /**
     * @var string
     */
    public $contents = '';

    /**
     * @var float
     */
    public $insuredValue = 0.0;

    /**
     * @var string
     */
    public $currency = 'EUR';

    /**
     * @var string[]
     */
    public $specialServices = [];

    /**
     * @var array
     */
    public $extra = [];

    /**
     * ShipmentRequest constructor.
     * @param string $service
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     */
    public function __construct(string $service, Address $sender, Address $recipient, Package $package)
    {
        $this->service = $service;
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->package = $package;

        $this->date = new DateTimeImmutable();
    }

}
