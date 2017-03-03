<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-02
 * Time: 17:08
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\DHL;

class Credentials
{

    /**
     * @var string
     */
    private $siteID;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $accountNumber;

    /**
     * DHLCredentials constructor.
     * @param string $siteID
     * @param string $password
     * @param string $accountNumber
     */
    function __construct(string $siteID, string $password, string $accountNumber)
    {
        $this->siteID = $siteID;
        $this->password = $password;
        $this->accountNumber = $accountNumber;
    }

    /**
     * @return string
     */
    public function getSiteID(): string
    {
        return $this->siteID;
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
    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

}
