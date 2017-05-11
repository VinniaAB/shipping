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
     * @var string
     */
    private $name;

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
     * @var string
     */
    private $reference;

    /**
     * Address constructor.
     * @param string $name
     * @param string[] $lines
     * @param string $zip
     * @param string $city
     * @param string $state
     * @param string $country
     * @param string $reference
     */
    function __construct(
        string $name,
        array $lines,
        string $zip,
        string $city,
        string $state,
        string $country,
        string $reference = ''
    )
    {
        $this->name = $name;
        $this->lines = $lines;
        $this->zip = $zip;
        $this->city = $city;
        $this->state = $state;
        $this->country = $country;
        $this->reference = $reference;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'lines' => $this->getLines(),
            'zip' => $this->getZip(),
            'city' => $this->getCity(),
            'state' => $this->getState(),
            'country' => $this->getCountry(),
            'reference' => $this->getReference(),
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new Address(
            $data['name'],
            $data['lines'],
            $data['zip'],
            $data['city'],
            $data['state'],
            $data['country'],
            $data['reference'] ?? ''
        );
    }

}
