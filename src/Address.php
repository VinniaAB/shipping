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
    public $name;

    /**
     * @var string[]
     */
    public $lines;

    /**
     * @var string
     */
    public $zip;

    /**
     * @var string
     */
    public $city;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $countryCode;

    /**
     * @var string
     */
    public $contactName;

    /**
     * @var string
     */
    public $contactPhone;

    /**
     * Address constructor.
     * @param string $name
     * @param string[] $lines
     * @param string $zip
     * @param string $city
     * @param string $state
     * @param string $countryCode
     * @param string $contactName
     * @param string $contactPhone
     */
    function __construct(
        string $name,
        array $lines,
        string $zip,
        string $city,
        string $state,
        string $countryCode,
        string $contactName = '',
        string $contactPhone = ''
    )
    {
        $this->name = $name;
        $this->lines = $lines;
        $this->zip = $zip;
        $this->city = $city;
        $this->state = $state;
        $this->countryCode = $countryCode;
        $this->contactName = $contactName;
        $this->contactPhone = $contactPhone;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'lines' => $this->lines,
            'zip' => $this->zip,
            'city' => $this->city,
            'state' => $this->state,
            'country_code' => $this->countryCode,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
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
            $data['country_code'],
            $data['contact_name'] ?? '',
            $data['contact_phone'] ?? ''
        );
    }

}
