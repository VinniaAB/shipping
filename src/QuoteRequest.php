<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use DateTimeImmutable;
use DateTimeInterface;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Centimeter;
use Vinnia\Util\Measurement\Inch;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Pound;
use Vinnia\Util\Measurement\Unit;

class QuoteRequest
{
    const PAYMENT_TYPE_SENDER = 'sender';
    const PAYMENT_TYPE_RECIPIENT = 'recipient';

    const UNITS_METRIC = 'metric';
    const UNITS_IMPERIAL = 'imperial';

    public Address $sender;
    public Address $recipient;

    /**
     * @var Parcel[]
     */
    public array $parcels;
    public DateTimeInterface $date;
    public float $value = 0.0;
    public string $contents = '';
    public float $insuredValue = 0.0;
    public string $currency = 'EUR';
    public bool $isDutiable = true;
    public string $incoterm = '';

    /**
     * @var string[]
     */
    public array $specialServices = [];

    /**
     * @var ExportDeclaration[]
     */
    public array $exportDeclarations = [];

    /**
     * @var mixed[]
     */
    public array $extra = [];
    public string $units = self::UNITS_METRIC;

    /**
     * QuoteRequest constructor.
     * @param Address $sender
     * @param Address $recipient
     * @param Parcel[] $parcels
     */
    public function __construct(Address $sender, Address $recipient, array $parcels)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->parcels = $parcels;

        $this->date = new DateTimeImmutable();
    }

    public function getPaymentTypeOfIncoterm(): string
    {
        // FIXME: is this default correct? we are essentially saying
        //        that DDP is the go-to duty payment type if we are
        //        not specifying an incoterm. since we are also defaulting
        //        isDutiable to true a more sane default might be to
        //        do with PAYMENT_TYPE_RECIPIENT.
        return !$this->incoterm || in_array(mb_strtoupper($this->incoterm, 'utf-8'), ['DDP'], true)
            ? static::PAYMENT_TYPE_SENDER
            : static::PAYMENT_TYPE_RECIPIENT;
    }

    /**
     * @return Unit[]
     */
    public function determineUnits(): array
    {
        return $this->units === QuoteRequest::UNITS_IMPERIAL
            ? [Inch::unit(), Pound::unit()]
            : [Centimeter::unit(), Kilogram::unit()];
    }
}
