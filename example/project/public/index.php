<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

require __DIR__.'/../vendor/autoload.php';

use App\Job\SimpleJob;
use App\Job\CoroutineJob;
use Littlesqx\AintQueue\Driver\DriverFactory;

$config = require __DIR__.'/../config/aint-queue.php';

$channel = 'example';
$driverOption = $config[$channel]['driver'] ?? [];

$queue = DriverFactory::make($channel, $driverOption);

$queue->push(function () {
    echo "Hello AintQueue\n";
});

$queue->push(new SimpleJob());
$queue->push(new CoroutineJob());

header('HTTP/1.1 201 Created');
