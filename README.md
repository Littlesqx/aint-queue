<h1 align="center"> :rocket: aint-queue </h1>

<p align="center"> 
    <p align="center"> A async-queue library built on top of swoole, flexable multi-consumer, coroutine supported. </p>
</p>

<p align="center"> 
    <img src="./screenshot.png" width="85%" />
</p>

## Required

- PHP 7.1+
- Swoole 4.3+
- Redis 3.2+ (redis driver)

## Install

```shell
$ composer require littlesqx/aint-queue -vvv
```

## Usage

### Config

By default, aint-queue will require `config/aint-queue.php` as default config. If not exist, `/vendor/littlesqx/aint-queue/src/Config/config.php` will be the final config file.

```php
<?php

use Littlesqx\AintQueue\Driver\Redis\Queue as RedisQueue;
use Littlesqx\AintQueue\Logger\DefaultLogger;

return [
    // channel_name => [...config]
    'default' => [
        'driver' => [
            'class' => RedisQueue::class,
            'connection' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => '0',
                // 'password' => 'password',
            ],
        ],
        'logger' => DefaultLogger::class,
        'pid_path' => '/var/run/aint-queue',
        'consumer' => [
            'sleep_seconds' => 1,
            'memory_limit' => 96,
            'dynamic_mode' => true,
            'capacity' => 6,
            'flex_interval' => 5 * 60,
            'min_worker_number' => 5,
            'max_worker_number' => 30,
            'max_handle_number' => 0,
        ],
        'job_snapshot' => [
            'interval' => 5 * 60,
            'handler' => [],
        ],
    ],
];

```

All the options:

| name | type | comment | default |
| :- | :- | :- | :- |
| channel | string | The queue unit, every queue pusher and queue listener work for. Multiple channel supported, use `--channel` option. | default |
| driver.class | string | Queue driver class, implements QueueInterface. | Redis |
| driver.connection | map | Queue driver's config. | |
| driver.handle_timeout | int | Every job's max handle seconds. | |
| pid_path | string | The path of listener master pid file. Noted that permission required. | /var/run/aint-queue |
| consumer.sleep_seconds | int | Sleep seconds after every empty pop from queue. | 1 |
| consumer.memory_limit | int | Mb. Worker will reload when its memory usage exceeds the limit. | 96 |
| consumer.dynamic_mode | bool | Determine whether worker's number flex dynamically. | true |  
| consumer.capacity | int | The capacity that every consumer can handle in health and in short time, it affects the worker number when dynamic-mode. | 5 |
| consumer.flex_interval | int | every `flex_interval` seconds monitor process try to flex the worker number. Only work when consumer.dynamic_mode = true. | 5 |
| consumer.min_worker_number | int | Min expansion. | 5 |
| consumer.max_worker_number | int | Max expansion. | 30 |
| consumer.max_handle_number | int | Current consumer's max job-handle time. `0` means no limit.| 0 |
| job_snapshot | map | Every `interval` seconds, `handles` will be executed. Handle must implements JobSnapshotterInterface.| |

### Queue pushing

You can use it in your project running via fpm/cli.

```php
<?php

use Littlesqx\AintQueue\Driver\DriverFactory;

$queue = DriverFactory::make($channel, $options);

// push a job
$queue->push(function () {
    echo "Hello aint-queue\n";
});

// push a delay job
$closureJob = function () {
    echo "Hello aint-queue delayed\n";
};
$queue->push($closureJob, 5);

// And class job are allowed.
// 1. Create a class which implements JobInterface, you can see the example in `/example`.
// 2. Noted that job pushed should be un-serialize by queue-listener,
//    it means queue-pusher and queue-listener are required to in the same project.                                          
// 3. You can see more examples in `example` directory.
```

### Manage listener

We recommend that using `Supervisor` to monitor and control the listener.

```bash
vendor/bin/aint-queue
```

```bash
Console Tool

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help                 Displays help for a command
  list                 Lists commands
 queue
  queue:clear          Clear the queue.
  queue:reload-failed  Reload all the failed jobs onto the waiting queue.
  queue:status         Get the execute status of specific queue.
 worker
  worker:listen        Listen the queue.
  worker:reload        Reload worker for the queue.
  worker:run           Run the specific job.
  worker:stop          Stop listening the queue.
```

## Testing

```bash
composer test
```
## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/littlesqx/aint-queue/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/littlesqx/aint-queue/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-2, PSR-12 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT
