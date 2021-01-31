<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\TNT;

class Credentials
{
    private string $username;
    private string $password;
    private string $accountNumber;
    private string $accountCountry;

    public function __construct(string $username, string $password, string $accountNumber, string $accountCountry)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accountNumber = $accountNumber;
        $this->accountCountry = $accountCountry;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountCountry(): string
    {
        return $this->accountCountry;
    }
}
