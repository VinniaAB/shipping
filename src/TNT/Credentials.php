<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-09
 * Time: 00:40
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\TNT;

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
    private $accountNumber;

    /**
     * @var string
     */
    private $accountCountry;

    /**
     * Credentials constructor.
     * @param string $username
     * @param string $password
     */
    function __construct(string $username, string $password, string $accountNumber, string $accountCountry)
    {
        $this->username = $username;
        $this->password = $password;
        $this->accountNumber = $accountNumber;
        $this->accountCountry = $accountCountry;
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
    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    /**
     * @return string
     */
    public function getAccountCountry(): string
    {
        return $this->accountCountry;
    }

}
