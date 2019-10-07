<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Psr\Log\LoggerInterface;
use Swoole\{Coroutine, Process, Runtime, Timer};
use Littlesqx\AintQueue\Driver\Redis\Queue;
use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Logger\DefaultLogger;


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

        $this->logger = new DefaultLogger();
        $this->workerDirector = new WorkerDirector($this->queue, $this->logger, $options['worker'] ?? []);
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
            throw new RuntimeException("Listener for queue:{$this->queue->getChannel()} is running!");
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

        return $root."/{$this->queue->getChannel()}-master.pid";
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
        $handlers = $this->options['job_snapshot']['handler'] ?? [];
        if (!empty($handlers)) {
            $interval = (int) $this->options['job_snapshot']['interval'] ?? 60 * 5;
            Timer::tick(1000 * $interval, function () {
                $this->checkQueueStatus();
            });
        }
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

        $this->queue->retryReserved();

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
                    $this->logger->error('Job dispatch error, '.$t->getMessage(), [
                        'driver' => \get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $id ?? null,
                        'attempts' => $attempts ?? null,
                    ]);
                    !empty($id) && $this->queue->failed($id, $attempts ?? 0);
                }

                if ($this->memoryExceeded()) {
                    $this->logger->info('Memory exceeded, force to exit.');
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
            [$waiting, $reserved, $delayed, $done, $failed, $total] = $this->queue->status();
            $snapshot = compact('waiting', 'reserved', 'delayed', 'done', 'failed', 'total');
            $handlers = $this->options['job_snapshot']['handler'] ?? [];
            foreach ($handlers as $handler) {
                if (!\is_string($handler) || !\class_exists($handler)) {
                    $this->logger->warning('Invalid JobSnapshotHandler or class not exists.');
                    continue;
                }
                $handler = new $handler();
                if (!$handler instanceof JobSnapshotHandlerInterface) {
                    $this->logger->warning('JobSnapshotHandler must implement JobSnapshotHandlerInterface.');
                    continue;
                }
                $handler->handle($snapshot);
            }
        } catch (\Throwable $t) {
            $this->logger->error('Error when exec JobSnapshotHandler, '.$t->getMessage(), [
                'driver' => \get_class($this->queue),
                'channel' => $this->queue->getChannel(),
            ]);

            return;
        }
    }
}
