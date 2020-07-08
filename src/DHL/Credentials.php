<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\DHL;

class Credentials
{
    public string $siteID;
    public string $password;
    public string $accountNumber;

    public function __construct(string $siteID, string $password, string $accountNumber)
    {
        $this->siteID = $siteID;
        $this->password = $password;
        $this->accountNumber = $accountNumber;
    }
}
