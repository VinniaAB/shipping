<?php declare(strict_types=1);

namespace Vinnia\Shipping;

class ExactErrorFormatter implements ErrorFormatterInterface
{
    public function format(string $message): string
    {
        return $message;
    }
}
