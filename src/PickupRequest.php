<?php
/**
 * Created by PhpStorm.
 * User: Bro
 * Date: 04.09.2018
 * Time: 17:03
 */

namespace Vinnia\Shipping;

use DateTimeImmutable;


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


    /**
     * @var string
     */
    public $service;

    /**
     * @var Address
     */
    public $pickupAddress;

    /**
     * @var Address
     */
    public $requestorAddress;

    /**
     * @var DateTimeImmutable
     */
    public $earliestPickup;

    /**
     * @var DateTimeImmutable
     */
    public $latestPickup;

    /**
     * @var array
     */
    public $parcels;

    /**
     * @var float
     */
    public $insuredValue = 0.0;

    /**
     * @var string
     */
    public $currency = 'EUR';

    /**
     * @var string
     */
    public $notes = '';

    /**
     * @var
     */
    public $units = self::UNITS_METRIC;

    /**
     * @var string
     */
    public $locationType = self::LOCATION_TYPE_BUSINESS;

    /**
     * @var string
     */
    public $deliveryServiceType = self::DELIVERY_SERVICE_TYPE_DOOR_TO_DOOR;

    /**
     * PickupRequest constructor.
     * @param string $service
     * @param Address $address
     * @param DateTimeImmutable $earliestPickup
     * @param DateTimeImmutable $latestPickup
     * @param array $parcels
     */
    public function __construct(
        string $service,
        Address $requestorAddress,
        Address $pickupAddress,
        DateTimeImmutable $earliestPickup,
        DateTimeImmutable $latestPickup,
        array $parcels
    )
    {

        $this->service = $service;
        $this->pickupAddress = $pickupAddress;
        $this->requestorAddress = $requestorAddress;
        $this->parcels = $parcels;
        $this->earliestPickup = $earliestPickup;
        $this->latestPickup = $latestPickup;
    }
}