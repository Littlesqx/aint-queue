# Aint Queue

[![Build Status](https://travis-ci.org/Littlesqx/aint-queue.svg?branch=master)](https://travis-ci.org/Littlesqx/aint-queue)

基于 Swoole 的一个异步队列库，可弹性伸缩的工作进程池，工作进程协程支持。<a href="README.md">English README</a>

![img](./screenshot.png)

## 特性

- 默认 Redis 驱动
- 秒级延时任务
- 自定义重试次数和时间
- 自定义错误回调
- 支持任务执行中间件
- 自定义队列快照事件
- 弹性多进程消费
- 工作进程协程支持

## 环境

- PHP 7.2+
- Swoole 4.4+
- Redis 3.2+ (redis 驱动)

## 安装

```shell
$ composer require littlesqx/aint-queue -vvv
```

## 使用

### 配置

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
            'pool_size' => 8,
            'pool_wait_timeout' => 1,
            'handle_timeout' => 60 * 30,
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

### 消息推送

可以在 cli/fpm 运行模式下使用：

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

```

更建议使用类任务，这样功能上会更加完整，也可以获得更好的编码体验和性能。

- 创建的任务类需要继承 `JobInterface`，详细可参考 `/example`
- 注意任务必须能在生产者和消费者中（反）序列化，意味着需要在同一个项目
- 利用队列快照事件你可以实现队列实时监控，而利用任务中间件，你可以实现任务执行速率限制，任务执行日志等。

### 队列管理

推荐使用 `Supervisor` 等进程管理工具守护工作进程。

```bash
vendor/bin/aint-queue
```

```bash
AintQueue Console Tool

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

## 测试

```bash
composer test
```
## 贡献

可以通过以下方式贡献：

1. 通过 [issue tracker](https://github.com/littlesqx/aint-queue/issues) 提交 bug 或者建议给我们。
2. 回答 [issue tracker](https://github.com/littlesqx/aint-queue/issues) 中的问题或者修复 bug。
3. 更新和完善文档，或者提交一些改进的代码给我们。

贡献没有什么特别的要求，只需要保证编码风格遵循 PSR2/PSR12，排版遵循 [中文文案排版指北](https://github.com/sparanoid/chinese-copywriting-guidelines)。

## License

MIT
