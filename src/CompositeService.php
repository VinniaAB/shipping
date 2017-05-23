<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-04
 * Time: 17:08
 */

namespace Vinnia\Shipping;


use GuzzleHttp\Promise\PromiseInterface;
use Vinnia\Util\Collection;
use DateTimeInterface;

class CompositeService implements ServiceInterface
{

    /**
     * @var ServiceInterface[]
     */
    private $delegates;

    /**
     * CompositeService constructor.
     * @param ServiceInterface[] $delegates
     */
    function __construct(array $delegates)
    {
        $this->delegates = $delegates;
    }

    /**
     * @param Address $sender
     * @param Address $recipient
     * @param Package $package
     * @param array $options
     * @return PromiseInterface promise resolved with \Vinnia\Shipping\Quote[] on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package, array $options = []): PromiseInterface
    {
        return $this->aggregate('getQuotes', [$sender, $recipient, $package, $options])->then(function (array $data) {
            return (new Collection($data))->flatten()->value();
        });
    }

    /**
     * @param string $trackingNumber
     * @param array $options
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber, array $options = []): PromiseInterface
    {
        return $this->aggregate('getTrackingStatus', [$trackingNumber, $options])->then(function (array $trackings) {
            return $trackings[0] ?? null;
        });
    }

    /**
     * Execute a method on all child services and aggregate the results into a combined promise.
     * @param string $method
     * @param array $parameters
     * @return PromiseInterface promise resolved with \Vinnia\Shipping\Tracking[] on success
     */
    private function aggregate(string $method, array $parameters = []): PromiseInterface
    {
        /* @var PromiseInterface[] $promises */
        $promises = array_map(function (ServiceInterface $service) use ($method, $parameters): PromiseInterface {
            return call_user_func_array([$service, $method], $parameters);
        }, $this->delegates);

        // create an aggregate promise that will be fulfilled
        // when all service promises are either fulfilled or rejected
        $aggregate = \GuzzleHttp\Promise\settle($promises);

        return $aggregate->then(function (array $inspections) {
            $results = [];
            foreach ($inspections as $inspection) {
                if ($inspection['state'] === PromiseInterface::FULFILLED) {
                    // we expect the result to be an array, otherwise this won't work
                    $results[] = $inspection['value'];
                }
            }
            return $results;
        });
    }

    /**
     * Returns a new CompositeService that contains a subset of services from this one.
     * @param string[] $serviceClasses
     * @return CompositeService
     */
    public function withOnly(array $serviceClasses): self
    {
        $filtered = [];
        foreach ($this->delegates as $delegate) {
            foreach ($serviceClasses as $clazz) {
                if ($delegate instanceof $clazz) {
                    $filtered[] = $delegate;
                    break 1;
                }
            }
        }
        return new self($filtered);
    }

    /**
     * @return ServiceInterface[]
     */
    public function getDelegates(): array
    {
        return $this->delegates;
    }

    /**
     * @param ShipmentRequest $request
     * @return PromiseInterface
     * @internal param Address $sender
     * @internal param Address $recipient
     * @internal param Package $package
     * @internal param array $options
     */
    public function createShipment(ShipmentRequest $request): PromiseInterface
    {
        return $this->aggregate('createLabel', [$request])->then(function (array $labels) {
            return $labels[0] ?? null;
        });
    }

    /**
     * @param string $id
     * @param array $data
     * @return PromiseInterface
     */
    public function cancelShipment(string $id, array $data = []): PromiseInterface
    {
    }
}
