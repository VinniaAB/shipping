<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-22
 * Time: 21:37
 */

namespace Vinnia\Shipping;

use Vinnia\Util\Collection;
use SimpleXMLElement;

class Xml
{

    /**
     * @param array $data
     * @return bool
     */
    public static function isNumericKeyArray(array $data): bool
    {
        return (new Collection($data))->keys()->every(function ($value) {
            return is_int($value);
        });
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return string
     */
    private static function createNode(string $name, $value): string
    {
        if (is_array($value)) {
            // if the array is numerically indexed we want to
            // create multiple XML nodes.
            if (self::isNumericKeyArray($value)) {
                return self::createMultipleNodes($name, $value);
            }

            // otherwise just transform the array into nested XML
            $value = self::fromArray($value);
        }

        // finally create a simple string node
        return self::createStringNode($name, $value);
    }

    /**
     * @param string $name
     * @param string $value
     * @return string
     */
    private static function createStringNode(string $name, string $value): string
    {
        return "<$name>$value</$name>";
    }

    /**
     * @param string $name
     * @param array $values
     * @return string
     */
    private static function createMultipleNodes(string $name, array $values): string
    {
        $out = '';
        foreach ($values as $value) {
            $out .= self::createNode($name, $value);
        }
        return $out;
    }

    /**
     * @param array $data
     * @return string
     */
    public static function fromArray(array $data): string
    {
        $out = '';
        foreach ($data as $key => $value) {
            $out .= self::createNode($key, $value);
        }
        return $out;
    }

    /**
     * @param string $data
     * @return string
     */
    public static function cdata(string $data): string
    {
        return "<![CDATA[$data]]>";
    }

    /**
     * @param array $source
     * @return array
     */
    public static function removeKeysWithEmptyValues(array $source): array
    {
        $func = function (array &$slice) use (&$func): void {
            foreach ($slice as $key => &$value) {
                if ($value === [] || $value === null) {
                    unset($slice[$key]);
                }
                elseif (is_array($value)) {
                    $func($value);
                }
            }
        };
        $copy = $source;
        $func($copy);
        return $copy;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     */
    public static function toArray(SimpleXMLElement $xml): array
    {
        $func = function (array $slice, array &$out) use (&$func) {
            foreach ($slice as $key => $element) {
                $res = $element;
                if (($element instanceof SimpleXMLElement && count($element) > 0) || is_array($element)) {
                    $res = [];
                    $func((array) $element, $res);
                }
                else {
                    $res = (string) $res;
                }
                $out[$key] = $res;
            }
        };

        $out = [];
        $func((array) $xml, $out);

        return $out;
    }

}
