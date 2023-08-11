<?php

namespace Ideade\Timesync\Util\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractRequestMiddleware
{
    abstract protected function handleRequest(RequestInterface $request): void;
    abstract protected function handleResponse(ResponseInterface $response): void;

    public static function create(): static
    {
        return new static();
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $this->handleRequest($request);
            return $handler($request, $options)
                ->then(
                    function (ResponseInterface $response) {
                        $this->handleResponse($response);
                        return $response;
                    }
                );
        };
    }
}