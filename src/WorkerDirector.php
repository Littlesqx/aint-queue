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
     * @var WorkerInterface[]
     */
    protected $workers = [];

    /**
     * @var int[]
     */
    protected $workerPid = [];

    /**
     * @var bool
     */
    protected $workerReloadAble = true;

    /**
     * @const string
     */
    const WORKER_PROCESS = 'process-worker';

    /**
     * @const string
     */
    const WORKER_PROCESS_POOL = 'process-pool-worker';

    /**
     * @const string
     */
    const WORKER_CO = 'co-worker';

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

        $this->workers[self::WORKER_PROCESS] = new ProcessWorker($queue, $logger, $options['process_worker'] ?? []);
        $this->workers[self::WORKER_PROCESS_POOL] = new ProcessPoolWorker($queue, $logger, $options['process_pool_worker'] ?? []);
        $this->workers[self::WORKER_CO] = new CoroutineWorker($queue, $logger, $options['coroutine_worker'] ?? []);
    }

    /**
     * @return ProcessWorker
     */
    public function getProcessWorker(): ProcessWorker
    {
        return $this->workers[self::WORKER_PROCESS];
    }

    /**
     * @return ProcessPoolWorker
     */
    public function getProcessPoolWorker(): ProcessPoolWorker
    {
        return $this->workers[self::WORKER_PROCESS_POOL];
    }

    /**
     * @return CoroutineWorker
     */
    public function getCoroutineWorker(): CoroutineWorker
    {
        return $this->workers[self::WORKER_CO];
    }

    /**
     * Register signal, reload worker when exit.
     */
    protected function registerSignal(): void
    {
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $workerId = (int) $ret['pid'];
                if ($workerId <= 0 || false === ($workerName = \array_search($workerId, $this->workerPid, true))) {
                    $this->logger->error(\sprintf('Invalid ret when SIGCHLD recv, worker not match: %s', \json_encode($ret)));
                    break;
                }
                $this->logger->info(\sprintf('Worker: %s - ret = %s, exit.', $workerName, \json_encode($ret)));

                if ($this->workerReloadAble) {
                    // restart worker...
                    $this->workerPid[$workerName] = $this->workers[$workerName]->start();
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
        if ($this->options['process_worker']['enable'] ?? false) {
            $this->workerPid[self::WORKER_PROCESS] = $this->getProcessWorker()->start();
        }

        if ($this->options['process_pool_worker']['enable'] ?? false) {
            $this->workerPid[self::WORKER_PROCESS_POOL] = $this->getProcessPoolWorker()->start();
        }

        if ($this->options['coroutine_worker']['enable'] ?? false) {
            $this->workerPid[self::WORKER_CO] = $this->getCoroutineWorker()->start();
        }
    }

    /**
     * Dispatch job to executor.
     *
     * @param $messageId
     * @param $message
     *
     * @throws \Throwable
     */
    public function dispatch($messageId, $message): void
    {
        if ($message instanceof CoJobInterface) {
            $this->getCoroutineWorker()->receive($messageId);
        } elseif ($message instanceof AsyncJobInterface) {
            $this->getProcessPoolWorker()->receive($messageId);
        } else {
            $this->getProcessWorker()->receive($messageId);
        }
    }

    /**
     * Reload all workers.
     */
    public function reload(): void
    {
        $this->workerReloadAble = true;
        foreach ($this->workers as $worker) {
            $worker->isRunning() && $worker->wait();
        }
    }

    /**
     * Stop all workers.
     */
    public function stop(): void
    {
        $this->workerReloadAble = false;
        foreach ($this->workers as $worker) {
            $worker->isRunning() && $worker->stop();
        }
    }
}
