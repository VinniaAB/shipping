<?php
/**
 * Created by PhpStorm.
 * User: Bro
 * Date: 15.10.2018
 * Time: 12:48
 */

namespace Vinnia\Shipping;


class ExactErrorFormatter implements ErrorFormatterInterface
{

    public function format(string $message): string
    {
        return $message;
    }
}