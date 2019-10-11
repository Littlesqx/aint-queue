<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Exception\CoroutineNumberExceedException;
use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Helper\SwooleHelper;
use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\QueueInterface;
use Littlesqx\AintQueue\SyncJobInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process as SwooleProcess;
use Swoole\Runtime;
use Symfony\Component\Process\Process;

abstract class AbstractWorker implements WorkerInterface
{
    protected $name;

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
    protected $redirectStdinStdout = false;

    /**
     * @var int
     */
    protected $pipeType = 2;

    /**
     * @var bool
     */
    protected $enableCoroutine = true;

    /**
     * @var bool
     */
    protected $working = true;

    /**
     * @var bool
     */
    protected $workerReloadAble = false;

    public function __construct(QueueInterface $queue, LoggerInterface $logger, array $options = [])
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->options = $options;
    }

    /**
     * This function should be called after worker-process forked.
     */
    protected function initWorker()
    {
        $processName = "aint-queue - {$this->name} for {$this->queue->getChannel()}";
        $this->logger->info($this->name.' is started, process name: '.$processName);
        // set process name
        SwooleHelper::setProcessName($processName);
        // required
        Runtime::enableCoroutine();

        $this->queue->resetConnection();

        // register signal
        SwooleProcess::signal(SIGUSR1, function () {
            $this->working = false;
            $this->workerReloadAble = true;
        });

        // Catch warning, for example: \Swoole\Coroutine::create(): exceed max number of coroutine xxxx
        \set_error_handler(function ($errno, $errStr, $errFile, $errLine, array $errcontext) {
            // error was suppressed with the @-operator
            if (0 === \error_reporting()) {
                return false;
            }

            if (false !== \strpos($errStr, 'exceed max number of coroutine')) {
                throw new CoroutineNumberExceedException($errStr);
            }

            throw new \ErrorException($errStr, 0, $errno, $errFile, $errLine);
        });
    }

    /**
     * This function should be called after sub-process exits.
     */
    protected function exitWorker()
    {
        $processName = "aint-queue - {$this->name} for {$this->queue->getChannel()}";
        $this->logger->info($this->name.' is stopped, process name: '.$processName);
        $this->queue->destroyConnection();
    }

    /**
     * Start current worker.
     *
     * @return int
     *
     * @throws RuntimeException
     */
    public function start(): int
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Worker is running, do not start again!');
        }

        $this->process = new SwooleProcess([$this, 'work'], $this->redirectStdinStdout, $this->pipeType, $this->enableCoroutine);

        $this->pid = (int) $this->process->start();

        return $this->pid;
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
     * @throws RuntimeException
     */
    public function wait(): void
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Worker is already stop!');
        }

        SwooleProcess::kill($this->pid, SIGUSR1);
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
            if (!$messageId) {
                throw new InvalidJobException("Invalid message_id: {$messageId}.");
            }

            [$id, $attempts, $job] = $this->queue->get($messageId);

            if (null === $job) {
                throw new InvalidJobException('Job popped is null.');
            }

            if (\is_callable($job)) {
                $job($this->queue);
                $this->queue->remove($id);
            } elseif ($job instanceof JobInterface) {
                $job->handle($this->queue);
                $this->queue->remove($id);
            } else {
                $type = \is_object($job) ? \get_class($job) : \gettype($job);
                throw new InvalidJobException("Not supported job, type: {$type}.");
            }
        } catch (\Throwable $t) {
            if (!$t instanceof InvalidJobException && $job instanceof JobInterface && $job->canRetry($attempts, $t)) {
                $delay = \max($job->getNextRetryTime($attempts) - \time(), 0);
                $this->queue->release($id, $delay);
            } else {
                $payload = \json_encode([
                    'last_error' => \get_class($t),
                    'last_error_message' => $t->getMessage(),
                    'attempts' => $attempts,
                ]);
                $this->queue->failed($id, $payload);
            }
            $this->logger->error(\get_class($t).': '.$t->getMessage(), [
                'driver' => \get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
                'attempts' => $attempts ?? 0,
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
        $id = $attempts = $job = null;
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
                    \fwrite(\STDERR, $buffer);
                } else {
                    \fwrite(\STDOUT, $buffer);
                }
            });
        } catch (\Throwable $t) {
            if ($job instanceof JobInterface && $job->canRetry($attempts, $t)) {
                $delay = \max($job->getNextRetryTime($attempts) - \time(), 0);
                $id && $this->queue->release($id, $delay);
            } else {
                $payload = \json_encode([
                    'last_error' => \get_class($t),
                    'last_error_message' => $t->getMessage(),
                    'attempts' => $attempts,
                ]);
                $id && $this->queue->failed($id, $payload);
            }
            $this->logger->error(\get_class($t).': '.$t->getMessage(), [
                'driver' => \get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
            ]);
        }
    }
}
