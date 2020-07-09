<?php declare(strict_types=1);

namespace Vinnia\Shipping;

class Shipment
{

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $vendor;

    /**
     * Binary pdf data
     * @var string
     */
    public $labelData;

    /**
     * Raw data that was used to create this object
     * @var mixed
     */
    public $raw;

    /**
     * Label constructor.
     * @param string $id
     * @param string $vendor
     * @param string $labelData
     * @param mixed $raw
     */
    public function __construct(string $id, string $vendor, string $labelData, $raw = null)
    {
        $this->id = $id;
        $this->vendor = $vendor;
        $this->labelData = $labelData;
        $this->raw = $raw;
    }
}
