<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-05-22
 * Time: 21:48
 */

namespace Vinnia\Shipping\Tests;


use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\Xml;

class XmlTest extends TestCase
{

    public function xmlProvider()
    {
        return [
            [[
                'key' => 'value',
            ], '<key>value</key>'],
            [[
               'a' => [
                   'b' => 'c',
                   'd' => 'e',
               ],
            ], '<a><b>c</b><d>e</d></a>'],
            [[
                'a' => [
                    'one',
                    'two',
                ],
            ], '<a>one</a><a>two</a>'],
        ];
    }

    /**
     * @dataProvider xmlProvider
     * @param array $data
     * @param string $expected
     */
    public function testFromArray(array $data, string $expected)
    {
        $xml = Xml::fromArray($data);

        $this->assertEquals($expected, $xml);
    }

}
