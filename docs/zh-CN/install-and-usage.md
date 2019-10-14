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
            'sleep_seconds' => 2,
            'memory_limit' => 96, // Mb
            'dynamic_mode' => true,
            'min_worker_number' => 5,
            'max_worker_number' => 50,
        ],
    ],
];

```

- channel

  `aint-queue` 支持多 channel 隔离，意味着可以开启多个消费进程对不同类型的消息的消费。

- driver

  驱动配置，`class` 要求是实现了 `QueueInterface` 的类的类名，而 `connection` 是驱动的连接配置，里面的配置视驱动而定，没有固定的键。

- pid_path

  这是用来存储主进程的进程 id 文件目录，一般在 `/var/run/` 下，默认是 `/var/run/aint-queue`。 
  
- memory_limit
  
  由于 PHP 的先天劣势，程序写得不好的话可能会有缓慢的内存泄漏异常，可以设定一个最大值，程序达到后会自动退出，
  可以配合进程守护助手（例如 supervisor）达到自动重启。
  
- job_snapshot

  任务快照，可以设置 interval 调整定时器的执行间隔，每次执行都会将当前的运行状态传递给 handler, handler 
  需要实现 JobSnapshotHandlerInterface 接口。可以同过此机制达到队列的监控，比如当任务堆积过多，邮件通知开发者。、
  
- sleep_seconds

  监听者在做一个不断轮训的操作，如果没有任务等待，循环会不断 pop 操作，耗用比较多 cpu 资源，设定一定的休眠时间有一定帮助。

- worker

  - 工作进程数可以通过 `worker_number` 配置，当某个子进程内存超出 `memory_limit`，
    子进程将在执行完本次任务后重启并继续工作。 dynamic_mode 设置为 true 时，进程池大小将是动态的（min_worker_number ~ max_worker_number），
    反之，设置为 false 则固定为 min_worker_number。
  - 可以在工作进程使用 swoole 协程 api，应该确保执行任务的方法体返回时，子协程已经结束。  
  
  #### 使用
  
  ##### 管理队列
  
  可以使用 `/vendor/bin/aint-queue` 命令：
  
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
       queue:listen         Listen the queue.
       queue:reload         Reload worker for the queue.
       queue:reload-failed  Reload all the failed jobs onto the waiting queue.
       queue:run            Run a job pop from the queue.
       queue:status         Get the execute status of specific queue.
       queue:stop           Stop listening the queue.
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
  ┌─────────┬──────────┬─────────┬───────┬────────┬───────┐
  │ waiting │ reserved │ delayed │ done  │ failed │ total │
  ├─────────┼──────────┼─────────┼───────┼────────┼───────┤
  │ 0       │ 0        │ 0       │ 26000 │ 0      │ 26000 │
  └─────────┴──────────┴─────────┴───────┴────────┴───────┘
  ```
  
  - queue-stop，退出监听进程
  
  - queue-reload，重载工作进程

  - queue:reload-failed，重载失败任务
    
 ##### 任务入队
 
 > 请确保开启了对应的消费 worker
 
 - 获取队列实例
 
 ```php
 <?php
 
// require path to 'vendor/autoload.php'; 

 // $config = [...];
 // or $config = require .../config.php;
 $queue = \Littlesqx\AintQueue\Driver\DriverFactory::make(
     'default',
     $config['default']['driver']
 );
 ```
 
 > 以下的 Job 可以自定义，它实现了 JobInterface。
 
 ```php
 <?php
 
 // Closure job
 $queue->push(function () {
     echo "I am a job\n";
 });
 
 // Class job
 $queue->push(new Job());
 
 
 $queue->push(function () {
     echo "I am a delayed job\n";
 }, 10);
 
 ```
  