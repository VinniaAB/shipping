<?php declare(strict_types=1);

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
    public function __construct(array $errors, string $source)
    {
        parent::__construct(implode("\n", $errors));

        $this->errors = $errors;
        $this->source = $source;
    }
}
