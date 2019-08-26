<?php
declare(strict_types = 1);

namespace Vinnia\Shipping\Tests;


use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function removeKeysWithEmptyValuesProvider()
    {
        return [
            [[], [null]],
            [[], [[]]],
            [['a' => 1], ['a' => 1, 'b' => null, 'c' => []]],
            [['a' => []], ['a' => ['b' => []]]],
        ];
    }
    /**
     * @dataProvider removeKeysWithEmptyValuesProvider
     * @param array $expected
     * @param array $source
     */
    public function testRemoveKeysWithEmptyValues(array $expected, array $source)
    {
        $this->assertEquals($expected, removeKeysWithValues($source, [], null));
    }
}
