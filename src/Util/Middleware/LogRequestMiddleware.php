<?php

namespace Ideade\Timesync\Util\Middleware;

use Ideade\Timesync\Util\MessageToString;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LogRequestMiddleware extends AbstractRequestMiddleware
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {}

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    protected function handleRequest(RequestInterface $request): void
    {
        $this->logger->debug(MessageToString::requestToString($request));
    }

    protected function handleResponse(ResponseInterface $response): void
    {
        $this->logger->debug(MessageToString::responseToString($response));
    }
}