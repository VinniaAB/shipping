<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-08-17
 * Time: 21:22
 */
declare(strict_types = 1);

namespace Vinnia\Shipping;


use GuzzleHttp\Promise\PromiseInterface;

class CompositeTracker
{

    /**
     * @var ServiceInterface[]
     */
    private $services;

    /**
     * CompositeTracker constructor.
     * @param ServiceInterface[] $services
     */
    function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * @param string $trackingNumber
     * @param array $options vendor specific options
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
        }, $this->services);
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

}
