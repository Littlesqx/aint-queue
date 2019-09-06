<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/06.
 */

require __DIR__ . '/../vendor/autoload.php';

use Littlesqx\AintQueue\Driver\DriverFactory;
use Littlesqx\AintQueue\Driver\Redis\Queue;
use App\Job\SyncJob;
use App\Job\AsyncJob;
use App\Job\CoJob;

$config = require __DIR__ . '/../config/aint-queue.php';

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




