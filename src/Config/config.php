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
            ],
        ],
        'pid_path' => '/var/run/aint-queue',
        'worker' => [
            'consumer' => [
                'sleep_seconds' => 2,
                'memory_limit' => 96, // Mb
                'dynamic_mode' => true,
                'capacity' => 6, // The capacity that every consumer can handle in health and in short time,
                                 // it affects the worker number when dynamic-mode.
                'min_worker_number' => 5,
                'max_worker_number' => 30,
            ],
            'monitor' => [
                'job_snapshot' => [
                    'interval' => 5 * 60,
                    'handler' => [],
                ],
                'consumer' => [
                    'flex_interval' => 5 * 60, // only work when consumer.dynamic_mode = true
                ],
                'job' => [
                    'move_expired_interval' => 2,
                ],
            ],
        ],
    ],
];
