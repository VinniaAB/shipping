<?php
declare(strict_types=1);

namespace Vinnia\Shipping\DHL;

use DOMNode;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Vinnia\Shipping\ExportDeclaration;
use Vinnia\Shipping\Parcel;
use Vinnia\Shipping\Shipment;
use Vinnia\Shipping\ShipmentRequest;
use Vinnia\Shipping\ShipmentServiceInterface;
use Vinnia\Util\Arrays;
use Vinnia\Util\Measurement\Amount;
use Vinnia\Util\Measurement\Unit;
use Vinnia\Util\Text\Xml;
use Vinnia\Util\Text\XmlCallbackParser;

class ShipmentService extends ServiceLike implements ShipmentServiceInterface
{
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        $now = date('c');
        $parcels = array_map(function (Parcel $parcel) use ($request): Parcel {
            return $request->units == ShipmentRequest::UNITS_IMPERIAL
                ? $parcel->convertTo(Unit::INCH, Unit::POUND)
                : $parcel->convertTo(Unit::CENTIMETER, Unit::KILOGRAM);
        }, $request->parcels);

        $parcelsData = array_map(function (Parcel $parcel, int $idx): array {
            return [
                'PieceID' => $idx + 1,
                'PackageType' => 'YP',
                'Weight' => $parcel->weight->format(2),
                'Width' => $parcel->width->format(0),
                'Height' => $parcel->height->format(0),
                'Depth' => $parcel->length->format(0),
            ];
        }, $parcels, array_keys($parcels));

        $specialServices = $request->specialServices;

        // TODO: the signature service may or may not be broken
        // on the test endpoint. currently requests with signature
        // required enabled fails with the following error:
        //
        // <Condition>
        //    <ConditionCode>154</ConditionCode>
        //    <ConditionData>null field value is invalid</ConditionData>
        // </Condition>
        //
        // we're not sending any null values so it's difficult
        // to debug this.
        if ($request->signatureRequired && !in_array('SA', $request->specialServices)) {
            $specialServices[] = 'SA';
        }

        if ($request->insuredValue > 0) {
            $specialServices[] = 'II';
        }

        $lengthUnitName = $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'I' : 'C';
        $weightUnitName = $request->units == ShipmentRequest::UNITS_IMPERIAL ? 'L' : 'K';

        $countryNames = require __DIR__ . '/../../countries.php';

        // if we don't have an explicit contents declarations, default
        // to the concatenated description of all export declarations.
        $contents = $request->contents
            ?: implode(',', array_map(fn ($e) => $e->description, $request->exportDeclarations));

        // if we don't have a value specified, default to the sum of all
        // export declarations.
        $value = $request->value
            ?: array_reduce($request->exportDeclarations, fn ($carry, $e) => $carry + $e->value, 0.0);

        $data = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' => $now,
                    'MessageReference' => '123456789012345678901234567890',
                    'SiteID' => $this->credentials->siteID,
                    'Password' => $this->credentials->password,
                ],
                'MetaData' => static::getMetaData(),
            ],
            'LanguageCode' => 'en',
            'PiecesEnabled' => 'Y',
            'Billing' => [
                'ShipperAccountNumber' => $this->credentials->accountNumber,
                'ShippingPaymentType' => 'S',
                'BillingAccountNumber' => $this->credentials->accountNumber,
            ],
            'Consignee' => [
                'CompanyName' => $request->recipient->name,
                'AddressLine' => array_filter($request->recipient->lines),
                'City' => $request->recipient->city,
                'PostalCode' => $request->recipient->zip,
                'CountryCode' => $request->recipient->countryCode,
                'CountryName' => mb_substr($countryNames[$request->recipient->countryCode], 0, 35, 'utf-8'),
                'Contact' => [
                    'PersonName' => $request->recipient->contactName,
                    'PhoneNumber' => $request->recipient->contactPhone,
                ],
            ],
            'Dutiable' => [
                'DeclaredValue' => number_format($value, 2, '.', ''),
                'DeclaredCurrency' => $request->currency,
            ],
            'ExportDeclaration' => [
                'InvoiceNumber' => $request->reference,
                'InvoiceDate' => $request->date->format('Y-m-d'),
                'ExportLineItem' => array_map(function (int $key, ExportDeclaration $decl) use ($request, $weightUnitName): array {
                    $weight = [
                        'Weight' => $decl->weight
                            ->convertTo($request->units == ShipmentRequest::UNITS_IMPERIAL ? Unit::POUND : Unit::KILOGRAM)
                            ->format(2),
                        'WeightUnit' => $weightUnitName,
                    ];
                    return [
                        'LineNumber' => $key + 1,
                        'Quantity' => $decl->quantity,
                        'QuantityUnit' => 'PCS',
                        'Description' => $decl->description,
                        'Value' => number_format($decl->value, 2, '.', ''),
                        'Weight' => $weight,
                        'GrossWeight' => $weight,
                        'ManufactureCountryCode' => $decl->originCountryCode,
                    ];
                }, array_keys($request->exportDeclarations), $request->exportDeclarations),
            ],
            'Reference' => [
                'ReferenceID' => $request->reference,
            ],
            'ShipmentDetails' => [
                'NumberOfPieces' => count($request->parcels),
                'Pieces' => [
                    'Piece' => $parcelsData,
                ],
                'Weight' => array_reduce($parcels, function (Amount $carry, Parcel $parcel) {
                    return new Amount(
                        $carry->getValue() + $parcel->weight->getValue(),
                        $parcel->weight->getUnit()
                    );
                }, new Amount(0, ''))->format(2),
                'WeightUnit' => $weightUnitName,
                'GlobalProductCode' => $request->service,
                'Date' => $request->date->format('Y-m-d'),
                'Contents' => $contents,
                //'DoorTo' => 'DD',
                'DimensionUnit' => $lengthUnitName,
                'InsuredAmount' => number_format($request->insuredValue, 2, '.', ''),
                'IsDutiable' => $request->isDutiable ? 'Y' : 'N',
                'CurrencyCode' => $request->currency,
            ],
            'Shipper' => [
                'ShipperID' => $this->credentials->accountNumber,
                'CompanyName' => $request->sender->name,
                'AddressLine' => $request->sender->lines,
                'City' => $request->sender->city,
                'PostalCode' => $request->sender->zip,
                'CountryCode' => $request->sender->countryCode,
                'CountryName' => mb_substr($countryNames[$request->sender->countryCode], 0, 35, 'utf-8'),
                'Contact' => [
                    'PersonName' => $request->sender->contactName,
                    'PhoneNumber' => $request->sender->contactPhone,
                ],
            ],
            'SpecialService' => array_map(function (string $service): array {
                return [
                    'SpecialServiceType' => $service,
                ];
            }, $specialServices),
            'LabelImageFormat' => $request->labelFormat ?: 'PDF',
            'Label' => [
                'LabelTemplate' => $request->labelSize ?: '8X4_A4_PDF',
            ],
        ];

        // if we include these fields for a non-dutiable shipments
        // DHL will reject it for unknown reasons.
        if ($request->isDutiable) {
            $data['Billing']['DutyPaymentType'] = $request->getPaymentTypeOfIncoterm() === ShipmentRequest::PAYMENT_TYPE_SENDER
                ? 'S'
                : 'R';
            $data['Billing']['DutyAccountNumber'] = $request->getPaymentTypeOfIncoterm() === ShipmentRequest::PAYMENT_TYPE_SENDER
                ? $this->credentials->accountNumber
                : null;
        }

        if ($request->isDutiable && $request->incoterm) {
            $data['Dutiable']['TermsOfTrade'] = $request->incoterm;
        }

        if ($request->internationalTransactionNo) {
            $data['Dutiable']['Filing'] = [
                'FilingType' => 'ITN',
                'ITN' => $request->internationalTransactionNo,
            ];
        }

        foreach ($request->extra as $key => $value) {
            Arrays::set($data, $key, $value);
        }

        $data = removeKeysWithValues($data, [], null);
        $shipmentRequest = Xml::fromArray($data);

        $body = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<req:ShipmentRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="6.2">
{$shipmentRequest}
</req:ShipmentRequest>
EOD;

        return $this->guzzle->requestAsync('POST', $this->baseUrl, [
            'query' => [
                'isUTF8Support' => 'true',
            ],
            'headers' => [
                'Accept' => 'text/xml',
                'Content-Type' => 'text/xml',
            ],
            'body' => $body,
        ])->then(function (ResponseInterface $response) {
            $body = (string)$response->getBody();

            $number = '';
            $awbData = '';
            $parser = new XmlCallbackParser([
                'AirwayBillNumber' => function (DOMNode $node) use (&$number) {
                    $number = $node->textContent;
                },
                'OutputImage' => function (DOMNode $node) use (&$awbData) {
                    $awbData = $node->textContent;
                },
            ]);
            $parser->parse($body);

            if (!$number) {
                $this->throwError($body);
            }

            $data = base64_decode($awbData);

            return [new Shipment($number, 'DHL', $data, $body)];
        });
    }

    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
