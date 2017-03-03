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

## Testing
Copy `credentials.example.php` into `credentials.php` and enter your credentials. Then execute
```
vendor/bin/phpunit --verbose --debug
```

