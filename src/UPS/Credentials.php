<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\UPS;

class Credentials
{

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $accessLicense;

    public function __construct(string $username, string $password, string $accessLicense)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accessLicense = $accessLicense;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getAccessLicense(): string
    {
        return $this->accessLicense;
    }
}
