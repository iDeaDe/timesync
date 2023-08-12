<?php declare(strict_types=1);

namespace Ideade\Timesync\Util;

use Psr\Log\AbstractLogger;
use Stringable;

class FileLogger extends AbstractLogger
{
    /**
     * @var resource
     */
    private $logFile;

    public function __construct(string $location)
    {
        $this->logFile = fopen($location, 'ab+');
    }

    public function __destruct()
    {
        fclose($this->logFile);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $message = $this->getRecordPrefix($level) . $this->interpolate($message, $context) . PHP_EOL;

        fwrite(
            $this->logFile,
            $message
        );
    }

    private function interpolate(Stringable|string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || $val instanceof Stringable)) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr((string)$message, $replace);
    }

    private function getRecordPrefix(string $level): string
    {
        return sprintf(
            '[%s] [%s] ',
            date('Y-m-d H:i:s'),
            ucfirst($level)
        );
    }
}