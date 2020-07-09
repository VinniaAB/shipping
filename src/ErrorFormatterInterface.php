<?php declare(strict_types=1);

namespace Vinnia\Shipping;

interface ErrorFormatterInterface
{
    public function format(string $message): string;
}
