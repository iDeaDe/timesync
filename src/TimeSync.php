<?php declare(strict_types=1);

namespace Ideade\Timesync;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Utils;
use Ideade\Timesync\Entity\GroupedIssueEntry;
use Ideade\Timesync\Entity\TogglTimeEntry;
use Ideade\Timesync\Entity\Youtrack\Profile;
use Ideade\Timesync\Util\Env;
use Ideade\Timesync\Util\Middleware\LogRequestMiddleware;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[AsCommand('sync', 'Synchronize time entries')]
final class TimeSync extends SingleCommandApplication
{
    private bool $isDebug = false;

    private DateTimeZone    $timezone;
    private LoggerInterface $logger;
    private TogglApi        $toggl;
    private YoutrackApi     $youtrack;
    private Profile         $profile;

    public function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @throws \Exception
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        Env::checkDefined(['TOGGL_LOGIN', 'TOGGL_PASSWORD', 'YOUTRACK_URL', 'YOUTRACK_TOKEN']);

        $this->isDebug = $output->isDebug();
        $this->timezone = new DateTimeZone($input->getOption('timezone'));

        $options = [];

        if ($this->isDebug) {
            $handlerStack = new HandlerStack(Utils::chooseHandler());
            $handlerStack->push(LogRequestMiddleware::create()->setLogger($this->getLogger()));

            $options['handler'] = $handlerStack;
        }

        $client      = new Client($options);
        $httpFactory = new HttpFactory();
        $serializer  = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );

        $this->toggl = new TogglApi(
            $client,
            $httpFactory,
            $serializer,
            Env::get('TOGGL_LOGIN'),
            Env::get('TOGGL_PASSWORD')
        );

        $this->youtrack = new YoutrackApi(
            $client,
            $httpFactory,
            $httpFactory,
            $serializer,
            Env::get('YOUTRACK_URL'),
            Env::get('YOUTRACK_TOKEN')
        );

        $this->profile = $this->youtrack->getCurrentUserProfile();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $splitDates = new DatePeriod(
            new DateTimeImmutable($input->getOption('start'), $this->timezone),
            new DateInterval('P1D'),
            new DateTimeImmutable($input->getOption('end'), $this->timezone),
            DatePeriod::INCLUDE_END_DATE
        );

        foreach ($splitDates as $date) {
            $from = $date->modify('00:00');
            $to   = $date->modify('23:59:59');

            $this
                ->getLogger()
                ->info(
                    'Checking entries between {start} and {end}.',
                    [
                        'start' => $from->format(DateTimeInterface::RFC3339),
                        'end'   => $to->format(DateTimeInterface::RFC3339),
                    ]
                );

            $entries        = $this->toggl->getEntries($from, $to);
            $groupedEntries = $this->getGroupedEntries($entries);

            $tracked = 0;

            foreach ($groupedEntries as $groupedEntry) {
                $currentTaskMinutes = $this->getTrackedMinutes($groupedEntry->issue, $groupedEntry->date);

                $addMinutes = $groupedEntry->minutes - $currentTaskMinutes;

                if ($addMinutes <= 0) {
                    continue;
                }

                $this->getLogger()
                    ->info(
                        'Issue: {issue}, current minutes: {currentMin}, tracked minutes: {trackedMin}, to track: {toTrack}',
                        [
                            'issue'      => $groupedEntry->issue,
                            'currentMin' => $groupedEntry->minutes,
                            'trackedMin' => $currentTaskMinutes,
                            'toTrack'    => $addMinutes,
                        ]
                    );

                $this->youtrack->track($groupedEntry->issue, $groupedEntry->date, $addMinutes);
                ++$tracked;
            }

            if ($tracked === 0) {
                $this->getLogger()->info('Nothing to track for this period');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param TogglTimeEntry[] $entries
     * @return GroupedIssueEntry[]
     * @throws \Exception
     */
    private function getGroupedEntries(array $entries): array
    {
        $groupedEntries = [];

        foreach ($entries as $entry) {
            if ($entry->description === null) {
                continue;
            }

            $entryIssue = trim(explode(' ', $entry->description)[0]);
            $entryTime  = (new DateTimeImmutable($entry->start))
                ->setTimezone($this->timezone);

            $groupKey = $entryIssue . $entryTime->format('Y-m-d');

            if (!isset($groupedEntries[$groupKey])) {
                $groupedEntries[$groupKey] = new GroupedIssueEntry(
                    $entryIssue,
                    0,
                    $entryTime
                );
            }

            $groupedEntries[$groupKey]->minutes += $entry->duration;
        }

        foreach ($groupedEntries as $i => $entry) {
            $entry->minutes = (int)round($entry->minutes / 60);
        }

        return array_values($groupedEntries);
    }

    private function getTrackedMinutes(string $issue, DateTimeImmutable $date): int
    {
        $minutes = 0;
        $offset  = 0;

        do {
            $workItems = $this->youtrack->getIssueWorkItems($issue, $offset, 100);

            foreach ($workItems as $workItem) {
                if (
                    $this->profile->id !== $workItem->author->id
                    || date('Y-m-d', (int)($workItem->date / 1000)) !== $date->format('Y-m-d')
                ) {
                    continue;
                }

                $minutes += $workItem->duration->minutes;
            }

            $offset += 100;
        } while (count($workItems) === 100);

        return $minutes;
    }
}