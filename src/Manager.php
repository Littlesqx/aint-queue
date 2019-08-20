<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Logger\DefaultLogger;
use Littlesqx\AintQueue\Timer\TimerProcess;
use Psr\Log\LoggerInterface;
use Swoole\Process;

class Manager
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QueueInterface|AbstractQueue
     */
    protected $queue;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var TimerProcess
     */
    protected $timerProcess;

    public function __construct(QueueInterface $driver, array $options)
    {
        $this->queue = $driver;
        $this->options = $options;

        $this->logger = new DefaultLogger();
    }

    protected function registerSignal()
    {
        // force exit
        Process::signal(SIGTERM, function ($signo) {
        });
        // force killed
        Process::signal(SIGKILL, function ($signo) {
        });
        // custom signal - exit smoothly
        Process::signal(SIGUSR1, function ($signo) {
        });
        // custom signal - record process status
        Process::signal(SIGUSR2, function ($signo) {
        });
    }

    protected function registerTimer()
    {
        $this->timerProcess = new TimerProcess([
            // move expired job
            new Timer\TickTimer(1000, function () {
                $this->queue->moveExpired();
            }),
            // check queue status
            new Timer\TickTimer(1000 * 60 * 5, function () {
                $this->queue->checkStatus();
            }),
        ]);

        $this->timerProcess->start();
    }

    /**
     * Set a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
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
     * Listen the queue, to distribute job.
     */
    public function listen()
    {
        $this->registerSignal();
        $this->registerTimer();

        while (true) {
            echo "waiting... \n";
            [$id, $job] = $this->queue->pop();

            if (null === $job) {
                sleep($this->getSleepTime());
            }

            $this->distributeJob($id, $job);

            if ($this->memoryExceeded()) {
                echo "Memory Exceeded \n";
                $this->waitWorkers();
            }
        }
    }

    /**
     * Whether memory exceeded or not.
     *
     * @return bool
     */
    public function memoryExceeded(): bool
    {
        $usage = (memory_get_usage(true) / 1024 / 1024);
        return $usage >= $this->getMemoryLimit();
    }

    /**
     * Distribute jobs to Specific worker.
     *
     * @param $messageId
     * @param $job
     */
    public function distributeJob($messageId, $job): void
    {
        $worker = new SingleWorker();
        try {
            $worker->deliver($this->queue, $messageId, $job);
        } catch (\Throwable $throwable) {
            $this->getLogger()->error($throwable->getMessage());
        }
    }

    public function executeJob($messageId): void
    {
        [$id, $job] = $this->queue->get($messageId);

        if ($job === null) {
            // log
            return;
        }

        if (is_callable($job)) {
            $job();
        } elseif ($job instanceof JobInterface) {
            $job->handle($this->queue);
        } else {
            // log
        }
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
        return (int) ($this->options['sleep_time'] ?? 0);
    }

    /**
     * Wait all the worker finish, then exit.
     */
    public function waitWorkers(): void
    {
        $this->timerProcess->quit();
    }

    public function exitWorkers(): void
    {
        $this->timerProcess->quit();
    }

    public function exitMaster(): void
    {

    }

}
