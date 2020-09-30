<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\TimezoneDetector;

class TimezoneDetectorTest extends TestCase
{
    public TimezoneDetector $detector;

    public function setUp(): void
    {
        $this->detector = new TimezoneDetector([
            'n' => [
                'new york city' => 'America/New_York',
            ],
            's' => [
                'stockholm' => 'Europe/Stockholm',
            ],
        ]);
    }

    public function timezoneProvider()
    {
        return [
            ['stockholm', 'Europe/Stockholm'],
            ['stockhol', 'Europe/Stockholm'],
            ['new york', null],
            ['yee', null],
        ];
    }

    /**
     * @dataProvider timezoneProvider
     * @param string $city
     * @param string|null $expected
     */
    public function testDetectsTimezone(string $city, ?string $expected)
    {
        $this->assertSame($expected, $this->detector->findByCity($city));
    }
}
