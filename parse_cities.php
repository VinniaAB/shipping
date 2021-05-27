<?php declare(strict_types=1);

// 2020-09-30
// this is a script to parse data in the cities***.zip
// files from http://download.geonames.org/export/dump/.

require __DIR__ . '/vendor/autoload.php';

$handle = fopen($argv[1], 'rb');
$out = [];

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
}

fclose($handle);

ksort($out);

$template = <<<TXT
<?php declare(strict_types=1);

// build date: %s

return %s;

TXT;

echo sprintf($template, date('c'), var_export($out, true));
