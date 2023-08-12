<?php declare(strict_types=1);

namespace Ideade\Timesync;

use DateTimeImmutable;
use DateTimeInterface;
use Ideade\Timesync\Entity\TogglTimeEntry;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Spatie\Url\Url;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class TogglApi
{
    public function __construct(
        private ClientInterface         $client,
        private RequestFactoryInterface $requestFactory,
        private SerializerInterface     $serializer,
        private string                  $login,
        private string                  $password,
    ) {}

    /**
     * @return TogglTimeEntry[]
     * @throws ClientExceptionInterface
     */
    public function getEntries(DateTimeImmutable $from, DateTimeImmutable $to = null): array
    {
        $query = [
            'start_date' => $from->format(DateTimeInterface::RFC3339),
        ];

        if ($to !== null) {
            $query['end_date'] = $to->format(DateTimeInterface::RFC3339);
        }

        $request = $this->requestFactory
            ->createRequest(
                'GET',
                Url::fromString('https://api.track.toggl.com/api/v9/me/time_entries')
                    ->withQuery(http_build_query($query))
            )
            ->withHeader('Authorization', 'Basic ' . $this->getAuthorizationToken());

        $response = $this->client->sendRequest($request);

        return $this->serializer
            ->deserialize(
                (string)$response->getBody(),
                'Ideade\Timesync\Entity\TogglTimeEntry[]',
                'json'
            );
    }

    private function getAuthorizationToken(): string
    {
        return base64_encode(sprintf('%s:%s', $this->login, $this->password));
    }
}