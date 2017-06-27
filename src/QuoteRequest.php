<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-06-22
 * Time: 23:04
 */

namespace Vinnia\Shipping;

use DateTimeImmutable;
use DateTimeInterface;

class QuoteRequest
{

    const PAYMENT_TYPE_SENDER = 'sender';
    const PAYMENT_TYPE_RECIPIENT = 'recipient';

    /**
     * @var Address
     */
    public $sender;

    /**
     * @var Address
     */
    public $recipient;

    /**
     * @var Parcel
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
     * @var bool
     */
    public $isDutiable = true;

    /**
     * @var string
     */
    public $dutyPaymentType = self::PAYMENT_TYPE_RECIPIENT;

    /**
     * @var string
     */
    public $shipmentPaymentType = self::PAYMENT_TYPE_SENDER;

    /**
     * @var string
     */
    public $incoterm = '';

    /**
     * @var string[]
     */
    public $specialServices = [];

    /**
     * @var array
     */
    public $extra = [];

    /**
     * QuoteRequest constructor.
     * @param Address $sender
     * @param Address $recipient
     * @param Parcel $package
     */
    public function __construct(Address $sender, Address $recipient, Parcel $package)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->package = $package;

        $this->date = new DateTimeImmutable();
    }

}
