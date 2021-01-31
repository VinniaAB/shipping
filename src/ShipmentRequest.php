<?php declare(strict_types=1);

namespace Vinnia\Shipping;

class ShipmentRequest extends QuoteRequest
{
    public string $service;
    public string $reference = '';
    public ?string $labelFormat = null;
    public ?string $labelSize = null;
    public bool $signatureRequired = false;
    public string $internationalTransactionNo = '';

    /**
     * ShipmentRequest constructor.
     * @param string $service
     * @param Address $sender
     * @param Address $recipient
     * @param Parcel[] $parcels
     */
    public function __construct(string $service, Address $sender, Address $recipient, array $parcels)
    {
        $this->service = $service;

        parent::__construct($sender, $recipient, $parcels);
    }
}
