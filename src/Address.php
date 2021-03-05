<?php
declare(strict_types = 1);

namespace Vinnia\Shipping;

use JsonSerializable;
use Vinnia\Util\Collection;

class Address implements JsonSerializable
{
    public string $name;
    public string $address1;
    public string $address2;
    public string $address3;
    public string $zip;
    public string $city;
    public string $state;
    public string $countryCode;
    public string $contactName;
    public string $contactPhone;
    public string $contactEmail;

    public function __construct(
        string $name,
        string $address1,
        string $address2,
        string $address3,
        string $zip,
        string $city,
        string $state,
        string $countryCode,
        string $contactName = '',
        string $contactPhone = '',
        string $contactEmail = ''
    ) {
        $this->name = $name;
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
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
            'address1' => $this->address1,
            'address2' => $this->address2,
            'address3' => $this->address3,
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
            $data['address1'] ?? '',
            $data['address2'] ?? '',
            $data['address3'] ?? '',
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
            $this->address1 ?: '',
            $this->address2 ?: '',
            $this->address3 ?: '',
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
