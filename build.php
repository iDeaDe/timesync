<?php declare(strict_types=1);

$startTime = hrtime(true);

const OUT_FILENAME = 'timesync.phar';

echo 'Checking if old build exists' . PHP_EOL;
if (file_exists(OUT_FILENAME)) {
    echo 'Deleting old build' . PHP_EOL;
    unlink(OUT_FILENAME);
}

exec('composer install --no-dev --no-interaction');

//////////////////// BUILDING PHAR ARCHIVE ////////////////////

$phar = (new Phar(OUT_FILENAME))
    ->convertToExecutable(Phar::PHAR, Phar::NONE);

foreach (['vendor', 'src'] as $directory) {
    /** @var iterable<SplFileInfo> $directoryIterator */
    $directoryIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . $directory),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($directoryIterator as $item) {
        if (!$item->isDir()) {
            $phar->addFromString($item->getRealPath(), php_strip_whitespace((string)$item));
            echo 'Added file ' . $item->getRealPath() . PHP_EOL;
        }
    }
}

$mainFilePath = __DIR__ . '/run.php';
$phar->addFromString($mainFilePath, php_strip_whitespace($mainFilePath));

$mainFile = <<<PHP
#!/usr/bin/php
<?php
require '$mainFilePath';
__HALT_COMPILER();
?>
PHP;

$phar->setStub($mainFile);
$phar->stopBuffering();

chmod(OUT_FILENAME, 0500);

//////////////////// BUILD TIME INFO ////////////////////

$nanoseconds = hrtime(true) - $startTime;
$seconds = (int)($nanoseconds * 1e-9);
$minutes = (int)($seconds / 60);
$seconds -= $minutes * 60;

echo sprintf(
        'Builded file %s in %d minutes, %d seconds',
        OUT_FILENAME,
        $minutes,
        $seconds
    )
    . PHP_EOL;