### 目录

- [概览](./overview.md)
- [安装和使用](./install-and-usage.md)
- [致谢](./thanks.md)


### 安装和使用

#### 环境要求

- PHP 7.1+，建议 PHP 7.3
- Swoole 4.3+，建议 Swoole 4.4+
- Redis

#### 安装和配置

```bash
composer require littlesqx/aint-queue -vvv
```

`aint-queue` 默认读取与 `vendor` 目录同级的 `config` 目录下的 `aint-queue.php`， 
如果该文件不存在，package 内部的 `/src/Config/config.php` 将是最终的配置。

```php
<?php

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

```

- channel

  `aint-queue` 支持

- driver

  驱动配置，`class` 要求是实现了 `QueueInterface` 的类的类名，而 `connection` 是驱动的连接配置，里面的配置视驱动而定，没有固定的键。

- pid_path

  这是用来存储监听进程的进程 id 文件目录，一般在 `/var/run/` 下，默认是 `/var/run/aint-queue`。 
  
- memory_limit
  
  由于 PHP 的先天劣势，程序写得不好的话可能会有缓慢的内存泄漏异常，可以设定一个最大值，程序达到后会自动退出，
  可以配合进程守护助手（例如 supervisor）达到自动重启。
  
- sleep_seconds

  监听者在做一个不断轮训的操作，如果没有任务等待，循环会不断 pop 操作，耗用比较多 cpu 资源，设定一定的休眠时间有一定帮助。

- warning_thresholds

  监听进程还有一个定时器检测等待队列的数目，当等待数目达到阈值（`waiting_job_number`）时，会触发 `waring_handler` 事件的执行。

- worker

  - process_worker 是单进程消费模式（被消费的任务来源于闭包任务和实现了 `SyncJobInterface` 的类任务），该消费模式下，任务是一个接一个先后顺序执行的，
    所以请保证单个任务的执行时长不会太久，可以再类定义中声明，或者被 `max_execute_seconds` 默认值覆盖，超出时长后任务将执行失败，抛出一个 `TimeoutException` 异常。
  
  - process_pool_worker 是进程池消费模式（被消费的任务来源于实现了 `AsyncJobInterface` 的类任务），进程数可以通过 `worker_number` 配置，当某个子进程内存超出 `memory_limit`，子进程将在执行完本次任务后重启并继续工作。  
  
  - coroutine_worker 是协程消费模式（被消费的任务来源于实现了 `CoJobInterface` 的类任务），会为每一个任务创建一个协程环境，开发者可以在任务中使用 `swoole` 提供的协程 api。
  
  #### 使用
  
  ##### 管理队列
  
  可以使用 `/vendor/bin/aint-queue` 命令：
  
```bash
  
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
  help          Displays help for a command
  list          Lists commands
 queue
  queue:clear   Clear the queue.
  queue:listen  Listen the queue.
  queue:run     Run a job pop from the queue.
  queue:status  Get the execute status of specific queue.
  queue:stop    Stop listening the queue.
  
  ```
  
  - queue:clear，清空队列，请慎用。
  
  ```
  ~/aint-queue$ ./bin/aint-queue queue:clear
  
   Are you sure to clear the default-queue? (yes/no) [no]:
   >
  ```
  
  - queue:listen，开启一个常驻进程监听
  
  - queue:run，运行指定任务
  
  - queue:status，查看当前任务状态
  
  ```bash
  The master-process of default-queue is not running!
  The status of default-queue:
  ┌─────────┬─────────┬──────────┬──────┬───────┐
  │ waiting │ delayed │ reserved │ done │ total │
  ├─────────┼─────────┼──────────┼──────┼───────┤
  │ 0       │ 0       │ 0        │ 0    │ 0     │
  └─────────┴─────────┴──────────┴──────┴───────┘
  ```
  
  - queue-stop，退出监听进程
  
 ##### 任务入队
 
 > 请确保开启了对应的消费 worker
 
 - 获取队列实例
 
 ```php
 <?php
 
// require path to 'vendor/autoload.php'; 

 // $config = [...];
 // or $config = require .../config.php;
 $queue = \Littlesqx\AintQueue\Driver\DriverFactory::make(
     \Littlesqx\AintQueue\Driver\Redis\Queue::class,
     'default',
     $config['default']['driver']
 );
 ```
 
 > 以下的 SyncJob, AsyncJob 和 CoJob 均可以自定义，他们分别实现了 SyncJobInterface, AsyncJobInterface 和 CoJobInterface。
 - 单进程消费的任务
 
 ```php
 <?php
 
 // Closure job
 $queue->push(function ($queue) {
     echo "I am a job\n";
 });
 
 // Class job
 $queue->push(new \Littlesqx\AintQueue\Example\SyncJob());
 
 ```
 
 - 进程池消费的任务
 
 ```php
 <?php
 
 $queue->push(new \Littlesqx\AintQueue\Example\AsyncJob());
 
 ```
 
 - 协程消费的任务
 
 ```php
 <?php
 
 $queue->push(new \Littlesqx\AintQueue\Example\CoJob());
 
 ```
 
 - 延时任务
 
 ```php
 <?php
 
 $queue->delay(10)->push(function ($queue) {
     echo "I am a delayed job\n";
 });
 ```
  