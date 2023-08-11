<?php declare(strict_types=1);

namespace Ideade\Timesync\Util;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MessageToString
{
    public static function requestToString(RequestInterface $request): string
    {
        $message = $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
        $message .= self::convertCommon($request);

        return $message;
    }

    public static function responseToString(ResponseInterface $response): string
    {
        $message = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ) . PHP_EOL;
        $message .= self::convertCommon($response);

        return $message;
    }

    private static function convertCommon(RequestInterface|ResponseInterface $part): string
    {
        $message = '';

        foreach ($part->getHeaders() as $header => $value) {
            foreach ($value as $item) {
                $message .= sprintf('%s: %s', $header, $item) . PHP_EOL;
            }
        }

        $message .= $part->getBody();

        return $message;
    }
}