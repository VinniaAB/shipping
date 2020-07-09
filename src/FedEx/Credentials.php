<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\FedEx;

class Credentials
{

    /**
     * @var string
     */
    private $credentialKey;

    /**
     * @var string
     */
    private $credentialPassword;

    /**
     * @var string
     */
    private $accountNumber;

    /**
     * @var string
     */
    private $meterNumber;

    public function __construct(string $credentialKey, string $credentialPassword, string $accountNumber, string $meterNumber)
    {
        $this->credentialKey = $credentialKey;
        $this->credentialPassword = $credentialPassword;
        $this->accountNumber = $accountNumber;
        $this->meterNumber = $meterNumber;
    }

    /**
     * @return string
     */
    public function getCredentialKey(): string
    {
        return $this->credentialKey;
    }

    /**
     * @return string
     */
    public function getCredentialPassword(): string
    {
        return $this->credentialPassword;
    }

    /**
     * @return string
     */
    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    /**
     * @return string
     */
    public function getMeterNumber(): string
    {
        return $this->meterNumber;
    }
}
