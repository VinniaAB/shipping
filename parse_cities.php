<?php declare(strict_types=1);

// 2021-08-31
// this is a script to parse data in the cities***.zip
// files from http://download.geonames.org/export/dump/.
//
// usage:
//   php parse_cities.php [path_to_cities.txt] [path_to_admin1_codes.txt]
//

require __DIR__ . '/vendor/autoload.php';

if (count($argv) < 3) {
    echo "Usage: php parse_cities.php [path_to_cities.txt] [path_to_admin1_codes.txt]\n";
    exit(1);
}

$regions = [];
$handle = fopen($argv[2], 'rb');

while ($parts = fgetcsv($handle, 0, "\t")) {
    $code = $parts[0] ?? '';
    if (!$code) {
        continue;
    }
    $regions[$code] = $parts[1];
}

fclose($handle);

/**
 * @see http://download.geonames.org/export/dump/readme.txt
 * for the file layout.
 **/
$handle = fopen($argv[1], 'rb');
$out = [
    'CH' => [
        // the cities-files are using the french spelling
        // "geneve" which is not what we want.
        'geneva' => 'Europe/Zurich',
    ],
    'SE' => [
        'arlanda' => 'Europe/Stockholm',
    ],
];

while ($parts = fgetcsv($handle, 0, "\t")) {
    $tz = $parts[17] ?? '';

    if (!$tz) {
        continue;
    }

    $countryCode = $parts[8];

    $city = \Vinnia\Shipping\TimezoneDetector::normalize($parts[2]);

    // index the array by the first character of the city.
    if (!isset($out[$countryCode])) {
        $out[$countryCode] = [];
    }

    $out[$countryCode][$city] = $tz;

    // inject the timezone of the administrative division
    // for the found city. this may or may not be precise
    // because the division can possibly span multiple timezones.
    $regionCode = sprintf('%s.%s', $countryCode, $parts[10]);
    $regionName = \Vinnia\Shipping\TimezoneDetector::normalize($regions[$regionCode] ?? '');

    if ($regionName) {
        $out[$countryCode][$regionName] = $tz;
    }
}

fclose($handle);

ksort($out);

$template = <<<TXT
<?php declare(strict_types=1);

// build date: %s

return %s;

TXT;

echo sprintf($template, date('c'), var_export($out, true));
