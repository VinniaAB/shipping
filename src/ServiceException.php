<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-29
 * Time: 19:27
 */

namespace Vinnia\Shipping;

use Exception;

class ServiceException extends Exception
{

    /**
     * @var array
     */
    public $errors;

    /**
     * @var string
     */
    public $source;

    /**
     * ErrorBag constructor.
     * @param string[] $errors
     * @param string $source
     */
    function __construct(array $errors, string $source)
    {
        parent::__construct(implode("\n", $errors));

        $this->errors = $errors;
        $this->source = $source;
    }

}
