<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Logger\DefaultLogger;
use Littlesqx\AintQueue\Timer\TickTimerProcess;
use Psr\Log\LoggerInterface;
use Swoole\Process as SwooleProcess;
use Symfony\Component\Process\Process;

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
     * @var TickTimerProcess
     */
    protected $tickTimer;

    public function __construct(QueueInterface $driver, array $options = [])
    {
        $this->queue = $driver;
        $this->options = $options;

        $this->logger = new DefaultLogger();
    }

    protected function registerSignal(): void
    {
        // force exit
        SwooleProcess::signal(SIGTERM, function ($signo) {
        });
        // force killed
        SwooleProcess::signal(SIGKILL, function ($signo) {
        });
        // custom signal - exit smoothly
        SwooleProcess::signal(SIGUSR1, function ($signo) {
        });
        // custom signal - record process status
        SwooleProcess::signal(SIGUSR2, function ($signo) {
        });
    }

    protected function registerTimer(): void
    {
        $this->tickTimer = new TickTimerProcess([
            // move expired job
            new Timer\TickTimer(1000, function () {
                $this->queue->moveExpired();
            }),
            // check queue status
            new Timer\TickTimer(1000 * 60 * 5, function () {
                $this->queue->checkStatus();
            }),
        ]);

        $this->tickTimer->start();
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
     * Listen the queue, to distribute job.
     */
    public function listen(): void
    {
        $this->registerSignal();
        $this->registerTimer();
        $jobDistributor = new JobDistributor($this);

        while (true) {
            [$id, $job] = $this->queue->pop();

            if (null === $job) {
                sleep($this->getSleepTime());
                continue;
            }

            try {
                $jobDistributor->dispatch($id, $job);
            } catch (\Throwable $t) {
                $this->getLogger()->error('Job execute error, ' . $t->getMessage());
            }

            if ($this->memoryExceeded()) {
                $this->getLogger()->info('Memory exceeded, exit smoothly.');
                $this->waitWorkers();
                $this->exitMaster();
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
     * Execute job in current process.
     *
     * @param $messageId
     */
    public function executeJob($messageId): void
    {
        [$id, $job] = $this->queue->get($messageId);

        if ($job === null) {
            $this->getLogger()->error('Unresolvable job.', [
                'driver' => gettype($this->queue),
                'topic' => $this->queue->getTopic(),
                'message_id' => $id,
            ]);
            return;
        }

        if (is_callable($job)) {
            $job($this->queue);
        } elseif ($job instanceof JobInterface) {
            $job->handle($this->queue);
        } else {
            $this->getLogger()->error('Not supported job, type: ' . gettype($job) . '.', [
                'driver' => gettype($this->queue),
                'topic' => $this->queue->getTopic(),
                'message_id' => $id,
            ]);
        }
    }

    /**
     * Execute job in sub-process. (blocking)
     *
     * @param $messageId
     *
     * @throws RuntimeException
     */
    public function executeJobInSubProcess($messageId): void
    {
        $entry = EnvironmentHelper::getAppBinary();
        if (null === $entry) {
            throw new RuntimeException('Fail to get app entry file.');
        }

        $cmd = [
            EnvironmentHelper::getPhpBinary(),
            $entry,
            'queue:run',
            "--id={$messageId}",
            "--topic={$this->queue->getTopic()}",
        ];

        $process = new Process($cmd);


        // set timeout
        $timeout = $options['timeout'] ?? 0;
        if ($timeout > 0) {
            $process->setTimeout($timeout);
        }

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });
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
        return (int) max($this->options['sleep_time'] ?? 0, 0);
    }

    /**
     * Wait all the worker finish, then exit.
     */
    public function waitWorkers(): void
    {
        $this->tickTimer->stop();
    }

    public function exitWorkers(): void
    {
        $this->tickTimer->stop();
    }

    public function exitMaster(): void
    {
        exit(0);
    }

    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }

}
