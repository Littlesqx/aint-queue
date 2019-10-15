<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

use Littlesqx\AintQueue\Driver\Redis\Queue as RedisQueue;

return [
    // channel => [...config]
    'default' => [
        'driver' => [
            'class' => RedisQueue::class,
            'connection' => [
                // Dynamic, put everything you want here...
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => '0',
                // 'password' => 'password',
                // Required if you use some blocking operation.
                'read_write_timeout' => 0,
            ],
        ],
        'pid_path' => '/var/run/aint-queue',
        'worker' => [
            'consumer' => [
                'sleep_seconds' => 2,
                'memory_limit' => 96, // Mb
                'dynamic_mode' => true,
                'min_worker_number' => 5,
                'max_worker_number' => 10,
            ],
            'monitor' => [
                'job_snapshot' => [
                    'interval' => 5 * 60,
                    'handler' => [],
                ],
            ]
        ],
    ],
];
