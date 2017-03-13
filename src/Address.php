<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-01
 * Time: 14:07
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;

class Address implements JsonSerializable
{

    /**
     * @var string[]
     */
    private $lines;

    /**
     * @var string
     */
    private $zip;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $state;

    /**
     * @var string
     */
    private $country;

    /**
     * Address constructor.
     * @param string[] $lines
     * @param string $zip
     * @param string $city
     * @param string $state
     * @param string $country
     */
    function __construct(array $lines, string $zip, string $city, string $state, string $country)
    {
        $this->lines = $lines;
        $this->zip = $zip;
        $this->city = $city;
        $this->state = $state;
        $this->country = $country;
    }

    /**
     * @return string[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @return string
     */
    public function getZip(): string
    {
        return $this->zip;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->getLines(),
            'zip' => $this->getZip(),
            'city' => $this->getCity(),
            'state' => $this->getState(),
            'country' => $this->getCountry(),
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

}
