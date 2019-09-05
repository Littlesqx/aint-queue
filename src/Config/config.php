<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

use Littlesqx\AintQueue\Driver\Redis\Queue as RedisQueue;
use Littlesqx\AintQueue\Example\JobOverflowEvent;

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
        'memory_limit' => 512, // Mb
        'sleep_seconds' => 3,
        'warning_thresholds' => [
            'warning_handler' => [
                JobOverflowEvent::class,
            ],
            'waiting_job_number' => 50,
            'ready_job_number' => 50,
        ],
        'worker' => [
            'process_worker' => [
                'enable' => true,
                'max_execute_seconds' => 0,
            ],
            'process_pool_worker' => [
                'enable' => true,
                'memory_limit' => 512, // Mb
                'worker_number' => 3,
            ],
            'coroutine_worker' => [
                'enable' => true,
            ],
        ],
    ],
];
