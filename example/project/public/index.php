<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

require __DIR__.'/../vendor/autoload.php';

use App\Job\AsyncJob;
use App\Job\CoJob;
use App\Job\SyncJob;
use Littlesqx\AintQueue\Driver\DriverFactory;
use Littlesqx\AintQueue\Driver\Redis\Queue;

$config = require __DIR__.'/../config/aint-queue.php';

$channel = 'example';
$driverOption = $config[$channel] ?? [];

$queue = DriverFactory::make(Queue::class, $channel, $driverOption);

$queue->push(function () {
    echo "Hello AintQueue\n";
});

$queue->push(new SyncJob());
$queue->push(new AsyncJob());
$queue->push(new CoJob());

header('HTTP/1.1 201 Created');
