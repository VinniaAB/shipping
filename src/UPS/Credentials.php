<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\UPS;

class Credentials
{
    private string $username;
    private string $password;
    private string $accessLicense;

    public function __construct(string $username, string $password, string $accessLicense)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accessLicense = $accessLicense;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getAccessLicense(): string
    {
        return $this->accessLicense;
    }
}
