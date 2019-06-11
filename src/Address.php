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
use Vinnia\Util\Collection;

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
     * @var string
     */
    public $contactEmail;

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
     * @param string $contactEmail
     */
    function __construct(
        string $name,
        array $lines,
        string $zip,
        string $city,
        string $state,
        string $countryCode,
        string $contactName = '',
        string $contactPhone = '',
        string $contactEmail = ''
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
        $this->contactEmail = $contactEmail;
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
            'contact_email' => $this->contactEmail,
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
            $data['contact_phone'] ?? '',
            $data['contact_email'] ?? ''
        );
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $parts = [
            $this->name,
            $this->lines[0] ?? '',
            $this->lines[1] ?? '',
            $this->lines[2] ?? '',
            $this->zip . ' ' . $this->city,
            $this->state,
            $this->countryCode,
            $this->contactName,
            $this->contactPhone,
            $this->contactEmail,
        ];

        return (new Collection($parts))
            ->map(function (string $part) {
                return trim($part);
            })
            ->filter(function (string $part) {
                return !empty($part);
            })
            ->join("\n");
    }
}
