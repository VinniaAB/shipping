<?php
declare(strict_types=1);

namespace Vinnia\Shipping;

use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\PromiseInterface;

class CompositeTracker
{
    /**
     * @var ServiceInterface[]
     */
    private array $services;

    /**
     * @param ServiceInterface[] $services
     */
    public function __construct(array $services)
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
        return $this->aggregate('getTrackingStatus', [[$trackingNumber], $options])->then(function (array $trackings) {
            return $this->findSuccessOrFirst($trackings);
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
        $promises = array_map(function (ServiceInterface $service) use ($method, $parameters): PromiseInterface {
            return call_user_func_array([$service, $method], $parameters);
        }, $this->services);
        // create an aggregate promise that will be fulfilled
        // when all service promises are either fulfilled or rejected
        $aggregate = Utils::settle($promises);
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
     * Find the first successful match or return the first response.
     * @param array $trackings
     * @return TrackingResult|null
     */
    private function findSuccessOrFirst(array $trackings): ?TrackingResult
    {
        /**
         * @var TrackingResult $trackingResult
         */
        foreach ($trackings as $trackingResults) {
            // Might be more than one trackingResult from each vendor
            foreach ($trackingResults as $trackingResult) {
                if ($trackingResult && TrackingResult::STATUS_SUCCESS === $trackingResult->status) {
                    return $trackingResult;
                }
            }
        }
        //No successful tracking found - return the first one
        //Current returns the first element of the array regardless of the index
        return current($trackings[0]) ?? null;
    }
}
