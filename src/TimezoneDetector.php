<?php declare(strict_types=1);

namespace Vinnia\Shipping;

use Normalizer;

final class TimezoneDetector
{
    const EXPECTED_MINIMUM_SIMILARITY = 75.0;
    const EXPECTED_SIMILARITY_BY_LENGTH = [
        1 => 85.0,
        2 => 85.0,
        3 => 85.0,
        4 => 80.0,
        5 => 80.0,
        6 => 80.0,
        7 => self::EXPECTED_MINIMUM_SIMILARITY,
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $timezoneMap;

    /**
     * TimezoneDetector constructor.
     * @param array<string, string> $timezoneMap
     */
    public function __construct(array $timezoneMap)
    {
        $this->timezoneMap = $timezoneMap;
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

    public function find(string $location, string $countryCode = ''): ?TimezoneResult
    {
        $location = static::normalize($location);

        // if we have a proper country code we can speed up
        // the search by removing any other country.
        $map = isset($this->timezoneMap[$countryCode])
            ? [$countryCode => $this->timezoneMap[$countryCode]]
            : $this->timezoneMap;

        // attempt to find an exact match.
        $found = $map[$countryCode][$location] ?? null;

        if ($found) {
            return new TimezoneResult($found, $location, $location);
        }

        $expectedSimilarity = static::EXPECTED_SIMILARITY_BY_LENGTH[strlen($location)]
            ?? static::EXPECTED_MINIMUM_SIMILARITY;
        $matchedTimezone = '';
        $matchedLocation = '';
        $mostSimilarPct = 0.0;

        foreach ($map as $cities) {
            foreach ($cities as $city => $timezone) {
                if ($city === $location) {
                    return new TimezoneResult(
                        $timezone, $location, $city
                    );
                }

                similar_text($location, $city, $similarity);

                // if we have a country code we don't really care about the
                // string similarity - we just want the most probable match.
                //
                // however, if we don't have a country code we don't want
                // to randomly grab a timezone if the similarity is super low.
                if (($countryCode || $similarity > $expectedSimilarity) && $similarity > $mostSimilarPct) {
                    $matchedTimezone = $timezone;
                    $matchedLocation = $city;
                    $mostSimilarPct = $similarity;
                }
            }
        }

        return $matchedTimezone
            ? new TimezoneResult($matchedTimezone, $location, $matchedLocation)
            : null;
    }
}
