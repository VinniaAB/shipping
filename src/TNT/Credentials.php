<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\TNT;

class Credentials
{
    public string $username;
    public string $password;
    public string $accountNumber;
    public string $accountCountry;

    public function __construct(string $username, string $password, string $accountNumber, string $accountCountry)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accountNumber = $accountNumber;
        $this->accountCountry = $accountCountry;
    }
}
