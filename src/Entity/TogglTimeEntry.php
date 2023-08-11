<?php declare(strict_types=1);

namespace Ideade\Timesync\Entity;

final readonly class TogglTimeEntry
{
    public function __construct(
        public string  $at,
        public int     $duration,
        public string  $start,
        public ?string $description = null,
        public ?string $stop = null,
    ) {}
}