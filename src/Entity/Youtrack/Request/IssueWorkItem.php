<?php declare(strict_types=1);

namespace Ideade\Timesync\Entity\Youtrack\Request;

use Ideade\Timesync\Entity\Youtrack\Profile;

final readonly class IssueWorkItem
{
    public function __construct(
        public int           $date,
        public Profile       $author,
        public DurationValue $duration
    ) {}
}