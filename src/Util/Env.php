<?php declare(strict_types=1);

namespace Ideade\Timesync\Util;

use Ideade\Timesync\Exception\RequiredEnvMissingException;

final class Env
{
    public static function get(string $name, string $default = null): ?string
    {
        return $_ENV[$name] ?? $default;
    }

    public static function checkDefined(array $names): void
    {
        if (count($names) === 0) {
            return;
        }

        $notDefinedVariables = [];

        foreach ($names as $name) {
            if (!isset($_ENV[$name])) {
                $notDefinedVariables[] = $name;
            }
        }

        if (count($notDefinedVariables) !== 0) {
            throw new RequiredEnvMissingException(
                'Required variables are not defined: ' . implode(', ', $notDefinedVariables)
            );
        }
    }
}