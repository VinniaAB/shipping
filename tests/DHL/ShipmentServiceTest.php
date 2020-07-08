<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests\DHL;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Vinnia\Shipping\Address;
use Vinnia\Shipping\DHL\Credentials;
use Vinnia\Shipping\DHL\ShipmentService;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\Tests\GuzzleTrait;
use function GuzzleHttp\Psr7\stream_for;

class ShipmentServiceTest extends TestCase
{
    use GuzzleTrait;

    public function testCreateShipmentParsesResponseAndExtractsLabel()
    {
        $service = new ShipmentService(
            $this->createClient(),
            new Credentials('', '', ''),
            ShipmentService::URL_TEST
        );
        $address = Address::fromArray([
            'country_code' => 'US',
        ]);
        $request = new ShipmentRequest('', $address, $address, []);
        $this->responseQueue[] = new Response(200, [], stream_for(
            <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<Response>
  <AirwayBillNumber>MY_AWB_NUMBER</AirwayBillNumber>
  <OutputImage>TVlfTEFCRUxfSU1BR0U=</OutputImage>
</Response>
XML
        ));
        /* @var \Vinnia\Shipping\Shipment[] $shipments */
        $shipments = $service->createShipment($request)
            ->wait();

        $this->assertCount(1, $shipments);
        $this->assertSame('MY_AWB_NUMBER', $shipments[0]->id);
        $this->assertSame('MY_LABEL_IMAGE', $shipments[0]->labelData);
    }
}
