<?php declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\Parcel;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

class ParcelTest extends TestCase
{
    public function testGetVolume()
    {
        $parcel = new Parcel(
            new Amount(4.0, Unit::METER),
            new Amount(2.0, Unit::METER),
            new Amount(3.0, Unit::METER),
            new Amount(0.0, Unit::KILOGRAM)
        );
        $volume = $parcel->getVolume();
        $this->assertEquals(24.0, $volume);
    }

    public function testGetVolumeConvertsUnits()
    {
        $parcel = new Parcel(
            new Amount(50.0, Unit::CENTIMETER),
            new Amount(300.0, Unit::MILLIMETER),
            new Amount(2.0, Unit::METER),
            new Amount(0.0, Unit::KILOGRAM)
        );
        $volume = $parcel->getVolume();
        $this->assertEquals(0.3, $volume);
    }
}
