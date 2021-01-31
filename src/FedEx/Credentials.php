<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\FedEx;

class Credentials
{
    private string $credentialKey;
    private string $credentialPassword;
    private string $accountNumber;
    private string $meterNumber;

    public function __construct(string $credentialKey, string $credentialPassword, string $accountNumber, string $meterNumber)
    {
        $this->credentialKey = $credentialKey;
        $this->credentialPassword = $credentialPassword;
        $this->accountNumber = $accountNumber;
        $this->meterNumber = $meterNumber;
    }

    public function getCredentialKey(): string
    {
        return $this->credentialKey;
    }

    public function getCredentialPassword(): string
    {
        return $this->credentialPassword;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getMeterNumber(): string
    {
        return $this->meterNumber;
    }
}
