<?php declare(strict_types=1);

namespace Ideade\Timesync\Entity\Youtrack\Request;

final readonly class DurationValue
{
    public function __construct(
        public int $minutes
    ) {}
}