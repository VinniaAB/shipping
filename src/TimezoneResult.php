<?php declare(strict_types=1);

namespace Vinnia\Shipping;

final class TimezoneResult
{
    public string $timezone;
    public string $inputLocation;
    public string $matchedLocation;

    public function __construct(string $timezone, string $inputLocation, string $matchedLocation)
    {
        $this->timezone = $timezone;
        $this->inputLocation = $inputLocation;
        $this->matchedLocation = $matchedLocation;
    }
}
