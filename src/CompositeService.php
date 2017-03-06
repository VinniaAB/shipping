<?php
/**
 * Created by PhpStorm.
 * User: johan
 * Date: 2017-03-04
 * Time: 17:08
 */

namespace Vinnia\Shipping;


use GuzzleHttp\Promise\PromiseInterface;

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
     * @return PromiseInterface promise resolved with an array of \Vinnia\Shipping\Quote on success
     */
    public function getQuotes(Address $sender, Address $recipient, Package $package): PromiseInterface
    {
        return $this->aggregate('getQuotes', [$sender, $recipient, $package]);
    }

    /**
     * @param string $trackingNumber
     * @return PromiseInterface
     */
    public function getTrackingStatus(string $trackingNumber): PromiseInterface
    {
        return $this->aggregate('getTrackingStatus', [$trackingNumber]);
    }

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
                    $results = array_merge($results, $inspection['value']);
                }
            }
            return $results;
        });
    }
}
