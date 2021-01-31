<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;
use Vinnia\Util\Collection;

class Address implements JsonSerializable
{
    public string $name;
    public array $lines;
    public string $zip;
    public string $city;
    public string $state;
    public string $countryCode;
    public string $contactName;
    public string $contactPhone;
    public string $contactEmail;

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
    public function __construct(
        string $name,
        array $lines,
        string $zip,
        string $city,
        string $state,
        string $countryCode,
        string $contactName = '',
        string $contactPhone = '',
        string $contactEmail = ''
    ) {
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

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {
        return new Address(
            $data['name'] ?? '',
            $data['lines'] ?? ['', '', ''],
            $data['zip'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['country_code'],
            $data['contact_name'] ?? '',
            $data['contact_phone'] ?? '',
            $data['contact_email'] ?? ''
        );
    }

    public function __toString(): string
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
