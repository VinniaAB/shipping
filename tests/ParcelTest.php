<?php declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\Parcel;
use Vinnia\Util\Measurement\Centimeter;
use Vinnia\Util\Measurement\Kilogram;
use Vinnia\Util\Measurement\Length;
use Vinnia\Util\Measurement\Mass;
use Vinnia\Util\Measurement\Meter;
use Vinnia\Util\Measurement\Millimeter;
use Vinnia\Util\Measurement\Unit;

class ParcelTest extends TestCase
{
    public function testGetVolume()
    {
        $parcel = new Parcel(
            new Length(4.0, Meter::unit()),
            new Length(2.0, Meter::unit()),
            new Length(3.0, Meter::unit()),
            new Mass(0.0, Kilogram::unit())
        );
        $volume = $parcel->getVolume(Meter::unit());
        $this->assertEquals(24.0, $volume);
    }

    public function testGetVolumeConvertsUnits()
    {
        $parcel = new Parcel(
            new Length(50.0, Centimeter::unit()),
            new Length(300.0, Millimeter::unit()),
            new Length(2.0, Meter::unit()),
            new Mass(0.0, Kilogram::unit())
        );
        $volume = $parcel->getVolume(Meter::unit());
        $this->assertEquals(0.3, $volume);
    }
}
