<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use DateTimeImmutable;
use DateTimeInterface;

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

    /**
     * @return string
     */
    public function getPaymentTypeOfIncoterm(): string
    {
        return !$this->incoterm || in_array(mb_strtoupper($this->incoterm, 'utf-8'), ['DDP'], true)
            ? static::PAYMENT_TYPE_SENDER
            : static::PAYMENT_TYPE_RECIPIENT;
    }
}
