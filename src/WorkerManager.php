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
use Swoole\Process;
use Swoole\Timer;

class WorkerManager
{
    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var Worker[]
     */
    protected $workers = [];

    /**
     * @var int
     */
    protected $maxWorkerNum;

    /**
     * @var int
     */
    protected $minWorkerNum;

    /**
     * @var int
     */
    protected $workerCheckerTimer = 0;

    /**
     * WorkerManager constructor.
     *
     * @param LoggerInterface $logger
     * @param QueueInterface  $queue
     * @param array           $options
     */
    public function __construct(QueueInterface $queue, LoggerInterface $logger, array $options = [])
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->options = $options;
    }

    /**
     * Start worker.
     */
    public function start(): void
    {
        // init
        $this->maxWorkerNum = $this->options['max_worker_number'] ?? 50;
        $this->minWorkerNum = $this->options['min_worker_number'] ?? 4;
        for ($i = 0; $i < $this->minWorkerNum; ++$i) {
            $this->createWorker();
        }

        // register signal
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $pid = $ret['pid'] ?? -1;
                $reload = 1 !== (int) ($ret['code'] ?? 0);
                if (isset($this->workers[$pid])) {
                    $this->logger->info("worker#{$pid} for {$this->queue->getChannel()} is stopped.");
                    unset($this->workers[$pid]);
                    $reload && $this->createWorker();
                }
            }
        });

        if ($this->options['dynamic_mode'] ?? false) {
            // check worker status, create or release workers
            $this->workerCheckerTimer = Timer::tick(1000 * 60 * 5, function () {
                [$waiting] = $this->queue->status();

                $healthWorkerNumber = max($this->minWorkerNum, min((int) ($waiting / 5), $this->maxWorkerNum));

                $differ = count($this->workers) - $healthWorkerNumber;

                while (0 !== $differ) {
                    // create more workers
                    $differ < 0 && $this->createWorker() && $differ++;
                    // release idle workers
                    $differ > 0 && $this->releaseWorker() && $differ--;
                }
            });
        }
    }

    /**
     * Reload all workers.
     */
    public function reload(): void
    {
        $this->refreshWorkers();
    }

    /**
     * Stop all workers.
     */
    public function stop(): void
    {
        if ($this->workerCheckerTimer > 0) {
            Timer::clear($this->workerCheckerTimer);
        }
        $this->destroyWorkers();
    }

    /**
     * Create a worker.
     *
     * @return bool
     */
    protected function createWorker(): bool
    {
        if (count($this->workers) >= $this->maxWorkerNum) {
            return false;
        }

        $worker = new Worker($this->queue, $this->logger, $this->options);
        $pid = $worker->start();
        $this->workers[$pid] = $worker;

        return true;
    }

    /**
     * Release a worker (at random).
     *
     * @return bool
     */
    protected function releaseWorker(): bool
    {
        $minWorker = $this->options['min_worker_number'] ?? 4;
        if (count($this->workers) <= $minWorker) {
            return false;
        }

        $selectedPid = array_rand($this->workers);
        Process::kill($selectedPid, 0) && Process::kill($selectedPid, SIGUSR2);

        return true;
    }

    /**
     * Exit worker after exec current job.
     */
    protected function refreshWorkers(): void
    {
        foreach ($this->workers as $pid => $worker) {
            Process::kill($pid, 0) && Process::kill($pid, SIGUSR1);
        }
    }

    /**
     * Force to exit worker.
     */
    protected function destroyWorkers(): void
    {
        foreach ($this->workers as $pid => $worker) {
            Process::kill($pid, 0) && Process::kill($pid, SIGTERM);
        }
    }
}
