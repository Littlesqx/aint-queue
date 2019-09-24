<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\QueueInterface;
use Littlesqx\AintQueue\SyncJobInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process as SwooleProcess;
use Symfony\Component\Process\Process;

abstract class AbstractWorker implements WorkerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var SwooleProcess
     */
    protected $process;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var bool
     */
    protected $canContinue = true;

    public function __construct(array $options, LoggerInterface $logger, QueueInterface $queue, \Closure $closure, bool $enableCoroutine = false)
    {
        $this->options = $options;
        $this->logger = $logger;
        $this->queue = $queue;

        SwooleProcess::signal(SIGCHLD, function () {
            while ($ret = SwooleProcess::wait(false)) {
                $this->logger->info("Worker: {$this->getName()} - pid={$ret['pid']} exit.");
            }
        });

        $this->process = new SwooleProcess($closure, false, 1, $enableCoroutine);
    }

    /**
     * Start current worker.
     *
     * @return bool
     *
     * @throws RuntimeException
     */
    public function start(): bool
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Worker is running, do not start again!');
        }

        $this->pid = $this->process->start();

        $this->logger->info($this->getName().' - pid='.$this->pid.' start.');

        return $this->isRunning();
    }

    /**
     * Stop current worker.
     *
     * @throws RuntimeException
     */
    public function stop(): void
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Worker is already stop!');
        }
        SwooleProcess::kill($this->pid, SIGTERM);
    }

    /**
     * Wait worker stop and exit.
     *
     * @return bool
     *
     * @throws RuntimeException
     */
    public function wait(): bool
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Worker is already stop!');
        }

        SwooleProcess::kill($this->pid, SIGUSR2);

        return true;
    }

    /**
     * Whether current worker is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->pid > 0 && SwooleProcess::kill($this->pid, 0);
    }

    /**
     * Receive an task into current worker.
     *
     * @param int $messageId
     *
     * @throws \Throwable
     */
    public function receive($messageId): void
    {
        $this->queue->ready($messageId, $this->getName());
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
                $this->logger->error('Unresolvable job.', [
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
                $this->logger->error('Not supported job, type: '.$type.'.', [
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
            $this->logger->error(get_class($t).': '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
            ]);
        }
    }

    /**
     * Execute job in a new process. (blocking).
     *
     * @param $messageId
     *
     * @throws \Throwable
     */
    public function executeJobInProcess($messageId): void
    {
        $timeout = $this->options['max_execute_seconds'] ?? 60;
        [$id, $attempts, $job] = [null, null, null];
        try {
            [$id, $attempts, $job] = $this->queue->get($messageId);
            $job instanceof SyncJobInterface && $timeout = $job->getTtr();

            $entry = EnvironmentHelper::getAppBinary();
            if (null === $entry) {
                throw new RuntimeException('Fail to get app entry file.');
            }

            $cmd = [
                EnvironmentHelper::getPhpBinary(),
                $entry,
                'queue:run',
                "--id={$messageId}",
                "--channel={$this->queue->getChannel()}",
            ];

            $process = new Process($cmd);

            // set timeout
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
        } catch (\Throwable $t) {
            if ($job instanceof JobInterface && $job->canRetry($attempts, $t)) {
                $delay = max($job->getNextRetryTime($attempts) - time(), 0);
                $this->queue->release($id, $delay);
            } else {
                $this->queue->failed($id);
            }
            $this->logger->error(get_class($t).': '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
            ]);
        }
    }
}
