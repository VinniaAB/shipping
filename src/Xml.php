<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-22
 * Time: 21:37
 */

namespace Vinnia\Shipping;

class Xml
{

    /**
     * @param array $data
     * @return string
     */
    public static function fromArray(array $data): string
    {
        $out = '';
        foreach ($data as $key => $value) {
            $out .= "<$key>";
            $out .= is_array($value) ? self::fromArray($value) : (string) $value;
            $out .= "</$key>";
        }
        return $out;
    }

}