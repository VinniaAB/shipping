<?php
declare(strict_types=1);

namespace Vinnia\Shipping\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait GuzzleTrait
{
    /**
     * @var RequestInterface[]
     */
    public array $requests = [];

    /**
     * @var ResponseInterface[]
     */
    public array $responseQueue = [];

    public function setUp(): void
    {
        if ($this instanceof TestCase) {
            parent::setUp();
        }

        $this->requests = [];
        $this->responseQueue = [];
    }

    public function createClient(): ClientInterface
    {
        return new Client([
            'handler' => HandlerStack::create(function (RequestInterface $request, array $options = []) {
                $this->requests[] = $request;
                $response = array_shift($this->responseQueue);
                return Create::promiseFor($response);
            }),
        ]);
    }
}
