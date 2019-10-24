<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Driver\Redis\Queue;
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Logger\DefaultLogger;
use Littlesqx\AintQueue\Logger\LoggerInterface;
use Swoole\Process;

class Manager
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var WorkerManager
     */
    protected $workerManager;

    /**
     * @var int
     */
    protected $masterPid;

    /**
     * Manager constructor.
     * @param QueueInterface $driver
     * @param array $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(QueueInterface $driver, array $options = [])
    {
        $this->queue = $driver;
        $this->options = $options;

        $loggerClass = $this->options['logger'] ?? DefaultLogger::class;
        $this->logger = new $loggerClass;
        if (! $this->logger instanceof LoggerInterface) {
            throw new InvalidArgumentException(sprintf('[Error] logger %s must implement LoggerInterface', $loggerClass));
        }

        $this->masterPid = getmypid();

        $this->workerManager = new WorkerManager($this->queue, $this->logger, $options);
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
     * Whether current channel's master is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        $pidFile = $this->getPidFile();
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);

            return Process::kill($pid, 0);
        }

        return false;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * @return WorkerManager
     */
    public function getWorkerManager(): WorkerManager
    {
        return $this->workerManager;
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
        file_put_contents($pidFile, getmypid());
    }

    /**
     * Remove pidFile.
     */
    protected function removePidFile(): void
    {
        @unlink($this->getPidFile());
    }

    /**
     * Register signal handler.
     */
    protected function registerSignal(): void
    {
        // force exit
        Process::signal(SIGTERM, function () {
            $this->workerManager->stop();
            $this->exitMaster();
        });
        // custom signal - reload workers
        Process::signal(SIGUSR1, function () {
            $this->workerManager->reload();
        });

        // custom signal - reserve (a signal)
        Process::signal(SIGUSR2, function () {
        });
    }

    /**
     * Start monitor and consumer worker.
     *
     * @throws \Throwable
     */
    public function listen(): void
    {
        @swoole_set_process_name(sprintf('aint-queue: master#%s', $this->queue->getChannel()));

        $this->queue->retryReserved();

        $this->workerManager->start();

        $this->setupPidFile();

        $this->registerSignal();

        register_shutdown_function([$this, 'exitMaster']);
    }

    /**
     * Exit master process.
     */
    public function exitMaster(): void
    {
        $this->workerManager->stop();
        $this->removePidFile();
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
