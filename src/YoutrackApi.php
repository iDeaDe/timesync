<?php declare(strict_types=1);

namespace Ideade\Timesync;

use DateTimeImmutable;
use Ideade\Timesync\Entity\Youtrack\Profile;
use Ideade\Timesync\Entity\Youtrack\Request\DurationValue;
use Ideade\Timesync\Entity\Youtrack\Request\IssueWorkItem;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Url\Url;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class YoutrackApi
{
    public function __construct(
        private ClientInterface         $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface  $streamFactory,
        private SerializerInterface     $serializer,
        private string                  $baseUrl,
        private string                  $token
    ) {}

    public function getCurrentUserProfile(): Profile
    {
        $request = $this->requestFactory
            ->createRequest(
                'GET',
                $this
                    ->getMethodUrl('users/me')
                    ->withQuery(
                        http_build_query([
                            'fields' => implode(',', ['id', 'login', 'fullName', 'email', 'guest', 'online', 'banned'])
                        ])
                    )
            );

        $response = $this->client->sendRequest($this->prepareRequest($request));

        return $this->serializer
            ->deserialize(
                (string)$response->getBody(),
                Profile::class,
                'json',
            );
    }

    public function track(string $issue, DateTimeImmutable $date, int $minutes): void
    {
        $issueWorkItem = new IssueWorkItem(
            (int)$date->format('Uv'),
            $this->getCurrentUserProfile(),
            new DurationValue($minutes)
        );

        $request = $this->requestFactory
            ->createRequest(
                'POST',
                $this->getMethodUrl('issues/' . $issue . '/timeTracking/workItems')
            )
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($this->serializer->serialize($issueWorkItem, 'json')));

        $response = $this->client->sendRequest($this->prepareRequest($request));
    }

    /**
     * @return IssueWorkItem[]
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function getIssueWorkItems(string $issue, int $skip = null, int $top = null): array
    {
        $query = [
            'fields' => 'author(id,login,fullName,email,guest,online,banned),date,duration(minutes)',
        ];

        if ($skip !== null) {
            $query['$skip'] = $skip;
        }

        if ($top !== null) {
            $query['$top'] = $top;
        }

        $request = $this->requestFactory
            ->createRequest(
                'GET',
                $this->getMethodUrl('issues/' . $issue . '/timeTracking/workItems?' . http_build_query($query))
            );

        $response = $this->client->sendRequest($this->prepareRequest($request));

        return $this->serializer
            ->deserialize(
                (string)$response->getBody(),
                'Ideade\Timesync\Entity\Youtrack\Request\IssueWorkItem[]',
                'json'
            );
    }

    private function prepareRequest(RequestInterface $request): RequestInterface
    {
        return $request
            ->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('Accept', 'application/json');
    }

    private function getMethodUrl(string $method): UriInterface
    {
        return Url::fromString($this->baseUrl)
            ->withPath('/api/' . $method);
    }
}