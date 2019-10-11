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
        'job_snapshot' => [
            'interval' => 5 * 60,
            'handler' => [],
        ],
        'worker' => [
            'type' => 'coroutine',  // One of process, process-pool, coroutine, if not provided, process will be set as default.
            'sleep_seconds' => 2,
            'memory_limit' => 96, // Mb
            'max_execute_seconds' => 10, // enable for process worker
            'dynamic_mode' => true,      // enable for process-pool worker
            'min_worker_number' => 5,    // enable for process-pool worker
            'max_worker_number' => 50,   // enable for process-pool worker
            'max_coroutine' => 4096,     // enable for coroutine worker
        ],
    ],
];
