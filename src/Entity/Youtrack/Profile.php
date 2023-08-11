<?php declare(strict_types=1);

namespace Ideade\Timesync\Entity\Youtrack;

final readonly class Profile
{
    public function __construct(
        public string  $id,
        public string  $login,
        public string  $fullName,
        public bool    $online,
        public bool    $banned,
        public ?string $email = null,
        public ?bool   $guest = null,
    ) {}
}