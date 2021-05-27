<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use Normalizer;

final class TimezoneDetector
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $timezoneMap;
    protected float $matchThreshold;

    /**
     * TimezoneDetector constructor.
     * @param array<string, string> $timezoneMap
     * @param float $matchThreshold
     */
    public function __construct(array $timezoneMap, float $matchThreshold = 85.0)
    {
        $this->timezoneMap = $timezoneMap;
        $this->matchThreshold = $matchThreshold;
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);

        if (class_exists(Normalizer::class)) {
            // decompose the value into characters and diacritics.
            // this makes it possible to remove accents and such
            // without manually replacing the bytes.
            $value = Normalizer::normalize($value, Normalizer::FORM_D);
        }

        // remove anything that is not a letter, number or whitespace.
        $value = preg_replace('/[^A-Za-z0-9\s]/', '', $value);

        return strtolower($value);
    }

    public function findByCity(string $city): ?string
    {
        $city = static::normalize($city);

        foreach ($this->timezoneMap as $countryCode => $cities) {
            if ($match = $this->findMatchFromCities($cities, $city)) {
                return $match;
            }
        }

        return null;
    }

    public function findByCountryAndCity(string $countryCode, string $city): ?string
    {
        $city = static::normalize($city);
        $cities = $this->timezoneMap[$countryCode] ?? [];

        return $this->findMatchFromCities($cities, $city);
    }

    protected function findMatchFromCities(array $cities, string $city): ?string
    {
        if (isset($cities[$city])) {
            return $cities[$city];
        }

        // if we didn't find an exact match, do some fuzzy searching.
        foreach ($cities as $maybeCity => $tz) {
            similar_text($maybeCity, $city, $result);

            if ($result > $this->matchThreshold) {
                return $tz;
            }
        }

        return null;
    }
}
