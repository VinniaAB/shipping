<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use DateTimeImmutable;
use DateTimeInterface;
use Vinnia\Util\Measurement\Centimeter;
use Vinnia\Util\Measurement\Inch;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Pound;
use Vinnia\Util\Measurement\Unit;

class PickupRequest
{
    const UNITS_METRIC = 'metric';
    const UNITS_IMPERIAL = 'imperial';

    const LOCATION_TYPE_BUSINESS = 'business';
    const LOCATION_TYPE_RESIDENTIAL = 'residential';
    const LOCATION_TYPE_BUSINESS_RESIDENTIAL = 'business/residential';

    const DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR = 'door_to_door';
    const DELIVERY_SERVICE_TYPE_DOOR_TO_AIRPORT = 'door_to_airport';
    const DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR_NON_COMPLIANT = 'door_to_door_non_compliant';

    public string $service;
    public Address $pickupAddress;
    public Address $requestorAddress;
    public DateTimeInterface $earliestPickup;
    public DateTimeInterface $latestPickup;

    /**
     * @var Parcel[]
     */
    public array $parcels;
    public float $insuredValue = 0.0;
    public string $currency = 'EUR';
    public string $notes = '';
    public string $units = self::UNITS_METRIC;
    public string $locationType = self::LOCATION_TYPE_BUSINESS;
    public string $deliveryServiceType = self::DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR;

    /**
     * PickupRequest constructor.
     * @param string $service
     * @param Address $requestorAddress
     * @param Address $pickupAddress
     * @param DateTimeImmutable $earliestPickup
     * @param DateTimeImmutable $latestPickup
     * @param Parcel[] $parcels
     */
    public function __construct(
        string $service,
        Address $requestorAddress,
        Address $pickupAddress,
        DateTimeImmutable $earliestPickup,
        DateTimeImmutable $latestPickup,
        array $parcels
    ) {
        $this->service = $service;
        $this->pickupAddress = $pickupAddress;
        $this->requestorAddress = $requestorAddress;
        $this->parcels = $parcels;
        $this->earliestPickup = $earliestPickup;
        $this->latestPickup = $latestPickup;
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
