<?php
/**
 *
 */
declare(strict_types = 1);

namespace Vinnia\Shipping\DPD;

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
     * Credentials constructor.
     * @param string $username
     * @param string $password
     */
    function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
        
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


}