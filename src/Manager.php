<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Driver\Redis\Queue;
use Littlesqx\AintQueue\Event\HandlerInterface;
use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Logger\DefaultLogger;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Timer;

class Manager
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QueueInterface|AbstractQueue|Queue
     */
    protected $queue;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var WorkerDirector
     */
    protected $workerDirector;

    /**
     * @var bool
     */
    protected $listening = false;

    public function __construct(QueueInterface $driver, array $options = [])
    {
        $this->queue = $driver;
        $this->options = $options;

        $this->init();
    }

    protected function init()
    {
        $this->logger = new DefaultLogger();

        $this->workerDirector = new WorkerDirector($this->getQueue(), $this->getLogger(), $this->getOptions()['worker']);
    }

    /**
     * Setup pidFile.
     *
     * @throws RuntimeException
     */
    protected function setupPidFile(): void
    {
        $pidFile = $this->getPidFile();
        if ($this->isRunning()) {
            throw new RuntimeException("Listener for queue:{$this->getQueue()->getChannel()} is running!");
        }
        @\file_put_contents($pidFile, \getmypid());
    }

    /**
     * Get master pid file path.
     *
     * @return string
     */
    public function getPidFile(): string
    {
        $root = $this->options['pid_path'] ?? '';

        return $root."/{$this->getQueue()->getChannel()}-master.pid";
    }

    /**
     * Register signal handler.
     */
    protected function registerSignal(): void
    {
        // force exit
        Process::signal(SIGTERM, function () {
            $this->workerDirector->stop();
            $this->exitMaster();
        });
        // custom signal - reload workers
        Process::signal(SIGUSR1, function () {
            $this->workerDirector->reload();
        });
        // custom signal - record process status
        Process::signal(SIGUSR2, function () {
            // TODO
        });
    }

    /**
     * Register timer-process.
     */
    protected function registerTimer(): void
    {
        // move expired job
        Timer::tick(1000, function () {
            $this->queue->migrateExpired();
        });
        // check queue status
        Timer::tick(1000 * 60 * 5, function () {
            $this->checkQueueStatus();
        });
    }

    /**
     * Set a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get current work logger.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return WorkerDirector
     */
    public function getWorkerDirector(): WorkerDirector
    {
        return $this->workerDirector;
    }

    /**
     * Get current queue instance.
     *
     * @return QueueInterface|Queue
     */
    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }

    /**
     * Listen the queue, to distribute job.
     *
     * @throws \Throwable
     */
    public function listen(): void
    {
        $this->workerDirector->start();

        \register_shutdown_function([$this, 'exitMaster']);

        $this->setupPidFile();

        $this->registerSignal();

        $this->getQueue()->retryReserved();

        $this->listening = true;

        // required
        Runtime::enableCoroutine();

        Coroutine::create(function () {
            $this->registerTimer();
            while ($this->listening) {
                try {
                    [$id, $attempts, $job] = $this->queue->pop();
                    if (!$id) {
                        Coroutine::sleep($this->options['sleep_seconds'] ?? 1);
                        continue;
                    }
                    if (null === $job) {
                        throw new InvalidJobException('Job popped is null.');
                    }
                    $this->workerDirector->dispatch($id, $job);
                } catch (\Throwable $t) {
                    $this->getLogger()->error('Job dispatch error, '.$t->getMessage(), [
                        'driver' => \get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $id ?? null,
                        'attempts' => $attempts ?? null,
                    ]);
                    !empty($id) && $this->getQueue()->failed($id, $attempts ?? 0);
                }

                if ($this->memoryExceeded()) {
                    $this->getLogger()->info('Memory exceeded, force to exit.');
                    $this->exitMaster();
                }
            }
        });
    }

    /**
     * Whether memory exceeded or not.
     *
     * @return bool
     */
    public function memoryExceeded(): bool
    {
        $usage = EnvironmentHelper::getCurrentMemoryUsage();

        return $usage >= $this->getMemoryLimit();
    }

    /**
     * Get manager's memory limit.
     *
     * @return float
     */
    public function getMemoryLimit(): float
    {
        return (float) ($this->options['memory_limit'] ?? 1024);
    }

    /**
     * Get sleep time(s) after every pop.
     *
     * @return int
     */
    public function getSleepTime(): int
    {
        return (int) \max($this->options['sleep_seconds'] ?? 0, 0);
    }

    /**
     * Exit master process.
     */
    public function exitMaster(): void
    {
        Timer::clearAll();
        $this->workerDirector->stop();
        $this->listening = false;
        @\unlink($this->getPidFile());
        // TODO: report exec event
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * Whether current channel's master is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        $pidFile = $this->getPidFile();
        if (\file_exists($pidFile)) {
            $pid = (int) \file_get_contents($pidFile);

            return Process::kill($pid, 0);
        }

        return false;
    }

    /**
     * Check current queue's running status.
     */
    protected function checkQueueStatus()
    {
        try {
            [$waiting, $reserved, $delayed, $done, $failed, $total] = $this->getQueue()->status();
        } catch (\Throwable $t) {
            $this->getLogger()->error('Error when getting queue\'s status, '.$t->getMessage(), [
                'driver' => \get_class($this->queue),
                'channel' => $this->queue->getChannel(),
            ]);

            return;
        }

        $maxWaiting = $this->getOptions()['warning_thresholds']['waiting_job_number'] ?? PHP_INT_MAX;
        $maxReserved = $this->getOptions()['warning_thresholds']['reserved_job_number'] ?? PHP_INT_MAX;

        $waiting = \array_sum($waiting);

        if ($waiting >= $maxWaiting || $reserved >= $maxReserved) {
            $handlers = $this->getOptions()['warning_thresholds']['warning_handler'] ?? [];
            foreach ($handlers as $handlerClass) {
                if (\class_exists($handlerClass) && ($handler = new $handlerClass())
                    && $handler instanceof HandlerInterface
                ) {
                    try {
                        $message = 'Current waiting jobs\' number is '.$waiting.'!';
                        $handler->handle($message, null, \compact('waiting', 'delayed', 'reserved', 'done', 'failed', 'total'));
                    } catch (\Throwable $t) {
                        $this->getLogger()->error('Handler error, '.$t->getMessage(), [
                            'driver' => \get_class($this->queue),
                            'channel' => $this->queue->getChannel(),
                            'warning_message' => $message,
                        ]);
                    }
                }
            }
        }
    }
}
