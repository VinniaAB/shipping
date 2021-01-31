<?php declare(strict_types=1);

namespace Vinnia\Shipping;

class Shipment
{
    public string $id;
    public string $vendor;
    public string $labelData;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    public function __construct(string $id, string $vendor, string $labelData, $raw = null)
    {
        $this->id = $id;
        $this->vendor = $vendor;
        $this->labelData = $labelData;
        $this->raw = $raw;
    }
}
