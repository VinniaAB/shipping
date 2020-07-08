<?php
declare(strict_types=1);

namespace Vinnia\Shipping\DHL;

use DOMDocument;
use GuzzleHttp\ClientInterface;
use Vinnia\Shipping\ErrorFormatterInterface;
use Vinnia\Shipping\ExactErrorFormatter;
use Vinnia\Shipping\ServiceException;
use Vinnia\Util\Arrays;
use Vinnia\Util\Text\Xml;

abstract class ServiceLike
{
    const URL_TEST = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet';
    const URL_PRODUCTION = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';

    protected ClientInterface $guzzle;
    protected Credentials $credentials;
    protected string $baseUrl;
    protected ?ErrorFormatterInterface $errorFormatter;

    public function __construct(
        ClientInterface $guzzle,
        Credentials $credentials,
        string $baseUrl = self::URL_PRODUCTION,
        ?ErrorFormatterInterface $errorFormatter = null
    ) {
        $this->guzzle = $guzzle;
        $this->credentials = $credentials;
        $this->baseUrl = $baseUrl;
        $this->errorFormatter = $errorFormatter ?: new ExactErrorFormatter();
    }

    protected function throwError(string $body): void
    {
        $errors = $this->getErrors($body);
        throw new ServiceException($errors, $body);
    }

    /**
     * @param string $body
     * @return string[]
     */
    protected function getErrors(string $body): array
    {
        $xml = new DOMDocument('1.0', 'utf-8');
        $xml->loadXML($body, LIBXML_PARSEHUGE);

        $arrayed = Xml::toArray($xml);
        $conditions = Arrays::get($arrayed, 'Response.Status.Condition');

        if (!$conditions) {
            return [];
        }

        return array_map(function (array $item) {
            $message = $item['ConditionData'];
            $message = preg_replace('/\s+/', ' ', $message);
            $message = $this->errorFormatter->format($message);
            return $message;
        }, Arrays::isNumericKeyArray($conditions) ? $conditions : [$conditions]);
    }

    protected function getErrorsAndMaybeThrow(string $body): void
    {
        $errors = $this->getErrors($body);

        if (!empty($errors)) {
            throw new ServiceException($errors, $body);
        }
    }

    public static function getMetaData(): array
    {
        return [
            'SoftwareName' => mb_substr(sprintf('%s/PHP %s', PHP_OS, PHP_VERSION), 0, 30, 'utf-8'),
            'SoftwareVersion' => mb_substr((string) PHP_VERSION, 0, 10, 'utf-8'),
        ];
    }
}
