<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\UPS;

class Credentials
{
    public string $username;
    public string $password;
    public string $accessLicense;

    public function __construct(string $username, string $password, string $accessLicense)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accessLicense = $accessLicense;
    }
}
