<h1 align="center"> :rocket: aint-queue </h1>

<p align="center"> 
    <p align="center"> 基于 Swoole 的一个异步队列库，可弹性伸缩的工作进程池，工作进程协程支持。<a href="README.md">English README</a>
 </p>
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

默认读取配置路径： `config/aint-queue.php`， 不存在时读取 `/vendor/littlesqx/aint-queue/src/Config/config.php` 。

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
        'logger' => [
            'class' => DefaultLogger::class,
                'options' => [
                    'level' => \Monolog\Logger::DEBUG,
                ],
            ],
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

所有参数：

| name | type | comment | default |
| :---- | :---- | :---- | :---- |
| channel | string | 频道。队列的单位，每个频道内的消息对应着各自的消费者和生产者。支持多频道。在命令行使用 `--channel` 参数。 | default |
| driver.class | string | 队列驱动类，需要实现 QueueInterface。 | Redis |
| driver.connection | map | 驱动配置。 | |
| pid_path | string | 主进程的 PID 文件存储路径。注意运行用户需要可读写权限。 | /var/run/aint-queue |
| consumer.sleep_seconds | int | 当任务空闲时，每次 pop 操作后的睡眠秒数。 | 1 |
| consumer.memory_limit | int | 工作进程的最大使用内存，超出则重启。单位 MB。| 96 |
| consumer.dynamic_mode | bool | 是否开启自动伸缩工作进程。 | true |  
| consumer.capacity | int | 代表每个工作进程在短时间内并且健康状态下的最多处理消息数，它影响了工作进程的自动伸缩策略。 | 5 |
| consumer.flex_interval | int | 每 `flex_interval` 秒，监控进程尝试调整工作进程数（假设开启了自动伸缩工作进程）。 | 5 |
| consumer.min_worker_number | int | 工作进程最小数目。 | 5 |
| consumer.max_worker_number | int | 工作进程最大数目。 | 30 |
| consumer.max_handle_number | int | 当前工作进程最大处理消息数，超出后重启。0 代表无限制。| 0 |
| job_snapshot | map | 每隔 `job_snapshot.interval` 秒，`job_snapshot.handles` 会被依次执行。`job_snapshot.handles` 需要实现 JobSnapshotterInterface。| |

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
