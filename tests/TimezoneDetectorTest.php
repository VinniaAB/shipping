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
            'CA' => [
                'london' => 'America/Toronto',
            ],
            'GB' => [
                'london' => 'Europe/London',
            ],
            'SE' => [
                'stockholm' => 'Europe/Stockholm',
            ],
            'US' => [
                'new york city' => 'America/New_York',
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
    public function testDetectsTimezoneByCity(string $city, ?string $expected)
    {
        $this->assertSame($expected, $this->detector->findByCity($city));
    }

    public function testDetectsTimezoneByCountryAndCity()
    {
        $tz = $this->detector->findByCountryAndCity('CA', 'London');
        $this->assertSame('America/Toronto', $tz);

        $tz = $this->detector->findByCountryAndCity('GB', 'London');
        $this->assertSame('Europe/London', $tz);
    }
}
