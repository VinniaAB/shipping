<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\FedEx;

class Credentials
{
    public string $credentialKey;
    public string $credentialPassword;
    public string $accountNumber;
    public string $meterNumber;

    public function __construct(string $credentialKey, string $credentialPassword, string $accountNumber, string $meterNumber)
    {
        $this->credentialKey = $credentialKey;
        $this->credentialPassword = $credentialPassword;
        $this->accountNumber = $accountNumber;
        $this->meterNumber = $meterNumber;
    }
}
