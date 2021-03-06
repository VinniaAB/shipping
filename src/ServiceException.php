<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use Exception;

class ServiceException extends Exception
{
    /**
     * @var string[]
     */
    public array $errors;
    public string $source;

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
