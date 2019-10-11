<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Worker\CoroutineWorker;
use Littlesqx\AintQueue\Worker\ProcessPoolWorker;
use Littlesqx\AintQueue\Worker\ProcessWorker;
use Littlesqx\AintQueue\Worker\WorkerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process;

class WorkerDirector
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $workerType;

    /**
     * @var WorkerInterface
     */
    protected $worker;

    /**
     * @var int
     */
    protected $workerPid;

    /**
     * @var bool
     */
    protected $workerReloadAble = true;

    /**
     * @const string
     */
    const WORKER_PROCESS = 'process';

    /**
     * @const string
     */
    const WORKER_PROCESS_POOL = 'process-pool';

    /**
     * @const string
     */
    const WORKER_CO = 'coroutine';

    /**
     * WorkerDirector constructor.
     *
     * @param LoggerInterface $logger
     * @param QueueInterface  $queue
     * @param array           $options
     */
    public function __construct(QueueInterface $queue, LoggerInterface $logger, array $options = [])
    {
        $this->logger = $logger;
        $this->options = $options;

        $this->registerSignal();

        $this->workerType = $this->options['type'] ?? self::WORKER_PROCESS;

        if (self::WORKER_PROCESS_POOL === $this->workerType) {
            $this->worker = new ProcessPoolWorker($queue, $logger, $options);
        } elseif (self::WORKER_CO === $this->workerType) {
            $this->worker = new CoroutineWorker($queue, $logger, $options);
        } else {
            $this->worker = new ProcessWorker($queue, $logger, $options);
        }
    }

    /**
     * @return ProcessWorker
     */
    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    /**
     * Register signal, reload worker when exit.
     */
    protected function registerSignal(): void
    {
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $workerId = (int) $ret['pid'];
                if ($workerId <= 0 || $workerId !== $this->workerPid) {
                    $this->logger->error(\sprintf('Invalid ret when SIGCHLD recv, worker not match: %s', \json_encode($ret)));
                    break;
                }
                $this->logger->info(\sprintf('Worker: %s - ret = %s, exit.', $this->workerType, \json_encode($ret)));

                if ($this->workerReloadAble) {
                    // restart worker...
                    $this->workerPid = $this->worker->start();
                }
            }
        });
    }

    /**
     * Start worker.
     *
     * @throws Exception\RuntimeException
     */
    public function start(): void
    {
        $this->worker->start();
    }

    /**
     * Reload all workers.
     */
    public function reload(): void
    {
        $this->workerReloadAble = true;
        $this->worker->isRunning() && $this->worker->wait();
    }

    /**
     * Stop all workers.
     */
    public function stop(): void
    {
        $this->workerReloadAble = false;
        $this->worker->isRunning() && $this->worker->stop();
    }
}
