<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\TimezoneDetector;

final class TimezoneDetectorTest extends TestCase
{
    public TimezoneDetector $detector;

    public function setUp(): void
    {
        $this->detector = new TimezoneDetector(
            require __DIR__ . '/../timezones.php'
        );
    }

    public function timezoneProvider()
    {
        return [
            ['stockholm', '', 'Europe/Stockholm'],
            ['stock', 'SE', 'Europe/Stockholm'],
            ['stockhol', '', 'Europe/Stockholm'],
            ['London', 'CA', 'America/Toronto'],
            ['London', 'GB', 'Europe/London'],
            ['new york', '', 'America/New_York'],
            ['yee', '', null],
            ['EAST MIDLANDS', 'GB', 'Europe/London'],
            ['ONTARIO SERVICE AREA', 'CA', 'America/Toronto'],
            ['CINCINNATI HUB', 'US', 'America/New_York'],
            ['NEW YORK CITY GATEWAY', 'US', 'America/New_York'],
            ['SHANGHAI - CHINA MAINLAND', 'CN', 'Asia/Shanghai'],
            ['EAST CHINA AREA', 'CN', 'Asia/Shanghai'],
        ];
    }

    /**
     * @dataProvider timezoneProvider
     * @param string $city
     * @param string|null $expected
     */
    public function testDetectsTimezoneByCity(string $city, string $countryCode, ?string $expected)
    {
        $found = $this->detector->find($city, $countryCode);

        $this->assertSame($expected, $found->timezone ?? null);
    }
}
