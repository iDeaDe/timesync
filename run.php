#!/usr/bin/env php
<?php declare(strict_types=1);

use Dotenv\Dotenv;
use Ideade\Timesync\TimeSync;
use Symfony\Component\Console\Input\InputOption;

if (PHP_MAJOR_VERSION < 8 && PHP_MINOR_VERSION < 2) {
    echo 'PHP >= 8.2 required' . PHP_EOL;
    die(1);
}

$composerAutoloadFile = __DIR__ . '/vendor/autoload.php';

if (!file_exists($composerAutoloadFile)) {
    echo 'Install dependencies first!';
    die(1);
}

require $composerAutoloadFile;
unset($composerAutoloadFile);

if (class_exists('Dotenv\Dotenv')) {
    Dotenv::createImmutable(__DIR__)->safeLoad();
}

$application = (new TimeSync('sync'))
    ->setLogger(new \Ideade\Timesync\Util\FileLogger('./timesync.log'))
    ->setVersion('1.0.0')
    ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start date', 'today')
    ->addOption('end', null, InputOption::VALUE_REQUIRED, 'End date', 'today')
    ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone', 'Asia/Almaty')
    ->run();

