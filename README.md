# 3rdparty shipping lib
This is a library for rating & tracking packages from various shipping carriers.
The following carriers are supported:
- UPS
- DHL
- FedEx
- TNT
- DPD (Coming...)

**Note: the code in this library is a work in progress; BC breaks will happen on a regular basis.**

## Requirements
- php 7.2+
- ext-curl
- ext-dom
- ext-mbstring
- ext-xml

## Usage
All shipping services are built around a common interface:
```php
interface ServiceInterface
{

    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(QuoteRequest $request): PromiseInterface;

    /**
     * @param string $trackingNumber
     * @param array $options vendor specific options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface;

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface;

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface;
    
    /**
     * @param QuoteRequest $request
     * @return PromiseInterface promise resolved with an array of strings
     */
    public function getAvailableServices(QuoteRequest $request): PromiseInterface;

}

```

DHL example:

```php
use Vinnia\Shipping\DHL\Credentials as DHLCredentials;
use Vinnia\Shipping\DHL\Service as DHLService;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\Parcel;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;

$guzzle = new \GuzzleHttp\Client();
$credentials = new DHLCredentials('site_id', 'password', 'account_number');
$dhl = new DHLService($guzzle, $credentials);

$sender = new Address('', [], '97334', 'Luleå', '', 'SE');
$recipient = new Address('', [], '21115', 'Malmö', '', 'SE');
$parcel = Parcel::make(30, 30, 30, 5, Unit::CENTIMETER, Unit::KILOGRAM);

$dhl->getQuotes($sender, $recipient, $parcel)->then(function (array $quotes) {
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

