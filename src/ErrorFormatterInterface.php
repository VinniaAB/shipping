<?php
/**
 * Created by PhpStorm.
 * User: Bro
 * Date: 15.10.2018
 * Time: 12:47
 */

namespace Vinnia\Shipping;


interface ErrorFormatterInterface
{
    public function format(string $message): string;
}