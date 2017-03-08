# 3rdparty shipping lib
This is a library for rating & tracking packages from various shipping carriers.
The following carriers are supported:
- UPS
- DHL
- FedEx
- TNT

## Requirements
- php 7.1+
- ext-curl
- ext-xml
- ext-mbstring

## Usage
All shipping services are built around a common interface:
```php
interface ServiceInterface
{

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface;

}
```

DHL example:

```php
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\DHL\Service as DHLService;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Package;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

$guzzle = new \GuzzleHttp\Client();
$credentials = new DHLCredentials('site_id', 'password', 'account_number');
$dhl = new DHLService($guzzle, $credentials);

$sender = new Address([], '97334', 'Luleå', '', 'SE');
$recipient = new Address([], '21115', 'Malmö', '', 'SE');

$size = new Amount(30, Unit::CENTIMETER);
$weight = new Amount(5, Unit::KILOGRAM);
$package = new Package($size, $size, $size, $weight);

$dhl->getQuotes($sender, $recipient, $package)->then(function (array $quotes) {
    // do something with the quote array
    
    foreach ($quotes as $quote) {
        echo $quote->getProduct() . PHP_EOL;
    }
});

```
If you want to use the library synchronously, refer to the guzzle promise documentation. Simple example:
```php
$promise = $dhl->getQuotes(...);
$quotes = $promise->wait();

// do something with the quote array

foreach ($quotes as $quote) {
    echo $quote->getProduct() . PHP_EOL;
}
```

## Testing
Copy `credentials.example.php` into `credentials.php` and enter your credentials. Then execute
```
vendor/bin/phpunit --verbose --debug
```

