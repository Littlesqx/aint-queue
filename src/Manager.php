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
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Logger\DefaultLogger;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process as SwooleProcess;
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
        @file_put_contents($pidFile, getmypid());
    }

    /**
     * @throws RuntimeException
     */
    protected function setupWorker(): void
    {
        $this->workerDirector = new WorkerDirector($this->getOptions()['worker'], $this->getLogger(), $this->getQueue());
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
        SwooleProcess::signal(SIGTERM, function () {
            $this->workerDirector->stop();
            $this->exitMaster();
        });
        // force killed
        SwooleProcess::signal(SIGKILL, function () {
            $this->workerDirector->stop();
            $this->exitMaster();
        });
        // custom signal - exit smoothly
        SwooleProcess::signal(SIGUSR1, function () {
            $this->workerDirector->wait();
            $this->exitMaster();
        });
        // custom signal - record process status
        SwooleProcess::signal(SIGUSR2, function () {
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
        $this->setupWorker();

        $this->registerSignal();

        $this->getQueue()->retryReserved();

        $this->listening = true;

        // required
        Runtime::enableCoroutine();

        Coroutine::create(function () {
            $this->registerTimer();
            $this->setupPidFile();
            while ($this->listening) {
                try {
                    [$id, , $job] = $this->queue->pop();

                    if (null === $job) {
                        if ($id) {
                            $this->getLogger()->error('Invalid job, id = '.$id, [
                                'driver' => get_class($this->queue),
                                'channel' => $this->queue->getChannel(),
                                'message_id' => $id,
                            ]);
                            $this->getQueue()->failed($id);
                        } else {
                            Coroutine::sleep($this->getSleepTime());
                        }
                        continue;
                    }
                    $this->workerDirector->dispatch($id, $job);
                } catch (\Throwable $t) {
                    $this->getLogger()->error('Job dispatch error, '.$t->getMessage(), [
                        'driver' => get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $id ?? null,
                    ]);
                    !empty($id) && $this->getQueue()->failed($id);
                }

                if ($this->memoryExceeded()) {
                    $this->getLogger()->info('Memory exceeded, exit smoothly.');
                    $this->workerDirector->wait();
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
     * Execute job in current process.
     *
     * @param $messageId
     *
     * @throws \Throwable
     */
    public function executeJob($messageId): void
    {
        $id = $attempts = $job = null;

        try {
            [$id, $attempts, $job] = $this->queue->get($messageId);

            if (null === $job) {
                $this->getLogger()->error('Unresolvable job.', [
                    'driver' => get_class($this->queue),
                    'channel' => $this->queue->getChannel(),
                    'message_id' => $id,
                ]);

                return;
            }

            if (is_callable($job)) {
                $job($this->queue);
                $this->queue->remove($id);
            } elseif ($job instanceof JobInterface) {
                $job->handle($this->queue);
                $this->queue->remove($id);
            } else {
                $type = is_object($job) ? get_class($job) : gettype($job);
                $this->getLogger()->error('Not supported job, type: '.$type.'.', [
                    'driver' => get_class($this->queue),
                    'channel' => $this->queue->getChannel(),
                    'message_id' => $id,
                ]);
            }
        } catch (\Throwable $t) {
            if ($job instanceof JobInterface && $job->canRetry($attempts, $t)) {
                $delay = max($job->getNextRetryTime($attempts) - time(), 0);
                $this->queue->release($id, $delay);
            } else {
                $this->queue->failed($id);
            }
            $this->getLogger()->error(get_class($t).': '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
            ]);
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
        return (int) max($this->options['sleep_seconds'] ?? 0, 0);
    }

    /**
     * Exit master process.
     */
    public function exitMaster(): void
    {
        Timer::clearAll();
        $this->listening = false;
        @unlink($this->getPidFile());
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
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);

            return SwooleProcess::kill($pid, 0);
        }

        return false;
    }

    /**
     * Check current queue's running status.
     */
    protected function checkQueueStatus()
    {
        try {
            [$waiting, $delayed, $reserved, $done, $failed, $total] = $this->getQueue()->status();
        } catch (\Throwable $t) {
            $this->getLogger()->error('Error when getting queue\'s status, '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
            ]);

            return;
        }

        $maxWaiting = $this->getOptions()['warning_thresholds']['waiting_job_number'] ?? PHP_INT_MAX;

        if ($waiting >= $maxWaiting) {
            $handlers = $this->getOptions()['warning_thresholds']['warning_handler'] ?? [];
            foreach ($handlers as $handlerClass) {
                if (class_exists($handlerClass) && ($handler = new $handlerClass())
                    && $handler instanceof HandlerInterface
                ) {
                    try {
                        $message = 'current waiting jobs\' number is '.$waiting.'!';
                        $handler->handle($message, null, compact('waiting', 'delayed', 'reserved', 'done', 'failed', 'total'));
                    } catch (\Throwable $t) {
                        $this->getLogger()->error('Handler error, '.$t->getMessage(), [
                            'driver' => get_class($this->queue),
                            'channel' => $this->queue->getChannel(),
                            'warning_message' => $message,
                        ]);
                    }
                }
            }
        }
    }
}
