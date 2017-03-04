# 3rdparty shipping lib
This is a library for fetching shipment quotes from 3rdparty vendors.
Currently the following vendors are supported:
- UPS
- DHL
- Fedex

## Requirements
- php 7.1+
- ext-curl
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
$guzzle = new \GuzzleHttp\Client();
$credentials = new \Vinnia\Shipping\DHL\Credentials('site_id', 'password', 'account_number');
$dhl = new \Vinnia\Shipping\DHL\Service($guzzle, $credentials);

$sender = new \Vinnia\Shipping\Address([], '97334', 'Luleå', '', 'SE');
$recipient = new \Vinnia\Shipping\Address([], '21115', 'Malmö', '', 'SE');
$package = new \Vinnia\Shipping\Package(30, 30, 30, 5000);

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

