<?php declare(strict_types=1);

namespace Ideade\Timesync\Entity;

final class GroupedIssueEntry
{
    public function __construct(
        public string             $issue,
        public int                $minutes,
        public \DateTimeImmutable $date
    ) {}
}