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
        'memory_limit' => 96, // Mb
        'sleep_seconds' => 2,
        'job_snapshot' => [
            'interval' => 5 * 60,
            'handler' => [

            ]
        ],
        'worker' => [
            'process_worker' => [
                'enable' => true,
                'memory_limit' => 96, // Mb
                'max_execute_seconds' => 10,
            ],
            'process_pool_worker' => [
                'enable' => true,
                'dynamic_mode' => true,
                'memory_limit' => 96, // Mb
                'min_worker_number' => 5,
                'max_worker_number' => 50,
            ],
            'coroutine_worker' => [
                'enable' => true,
                'memory_limit' => 96, // Mb
                'max_coroutine' => 4096,
            ],
        ],
    ],
];
