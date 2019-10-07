<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\Helper\SwooleHelper;
use Littlesqx\AintQueue\WorkerDirector;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Timer;

class ProcessPoolWorker extends AbstractWorker
{
    /**
     * @var string
     */
    protected $name = WorkerDirector::WORKER_PROCESS_POOL;

    /**
     * @var Process[]
     */
    protected $pool = [];

    /**
     * @var int
     */
    protected $maxWorkerNum;

    /**
     * @var int
     */
    protected $minWorkerNum;

    /**
     * @var bool
     */
    protected $enableCoroutine = false;

    /**
     * Setup worker.
     */
    public function work(): void
    {
        // reset connection
        $this->queue->resetConnection();
        // set process name
        $processName = "aint-queue - {$this->name}:master for {$this->queue->getChannel()}";
        $this->logger->info($this->name.' is started, process name: '.$processName);
        SwooleHelper::setProcessName($processName);

        // init
        $this->maxWorkerNum = $this->options['max_worker_number'] ?? 50;
        $this->minWorkerNum = $this->options['min_worker_number'] ?? 4;
        for ($i = 0; $i < $this->minWorkerNum; ++$i) {
            $this->createWorker();
        }

        swoole_async_set(['enable_coroutine' => false]);
        // register signal
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $pid = $ret['pid'] ?? -1;
                $reload = 1 === (int) ($ret['code'] ?? 0);
                if (isset($this->pool[$pid])) {
                    $this->logger->info("aint-queue - {$this->name}:worker#{$pid} for {$this->queue->getChannel()} is stopped.");
                    unset($this->pool[$pid]);
                    $reload && $this->createWorker();
                }
            }
        });

        // smooth reload
        Process::signal(SIGUSR1, function () {
            $this->refreshPool();
        });

        // stop sub-worker
        Process::signal(SIGTERM, function () {
            $this->destroyPool();
            Timer::clearAll();
        });

        if ($this->options['dynamic_mode'] ?? false) {
            // check worker status, create or release workers
            Timer::tick(1000 * 60 * 5, function () {
                [[$beforeDispatch, $process, $processPool, $co]] = $this->queue->status();

                $healthWorkerNumber = max($this->minWorkerNum, min((int) ($processPool / 5), $this->maxWorkerNum));

                $differ = \count($this->pool) - $healthWorkerNumber;

                while ($differ !== 0) {
                    // create more workers
                    $differ < 0 && $this->createWorker() && $differ++;
                    // release idle workers
                    $differ > 0 && $this->releaseWorker() && $differ--;
                }
            });
        }
    }

    /**
     * @return bool
     */
    protected function createWorker(): bool
    {
        if (\count($this->pool) < $this->maxWorkerNum) {
            $process = new Process([$this, 'workStart'], $this->redirectStdinStdout, $this->pipeType, true);
            $pid = $process->start();
            $this->pool[$pid] = $process;

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function releaseWorker(): bool
    {
        $minWorker = $this->options['min_worker_number'] ?? 4;
        if (\count($this->pool) > $minWorker) {
            $selectedPid = \array_rand($this->pool);
            Process::kill($selectedPid, 0) && Process::kill($selectedPid, SIGUSR2);

            return true;
        }

        return false;
    }

    /**
     * Exit worker after exec current job.
     */
    protected function refreshPool(): void
    {
        foreach ($this->pool as $pid => $process) {
            Process::kill($pid, 0) && Process::kill($pid, SIGUSR1);
        }
    }

    /**
     * Force to exit worker.
     */
    protected function destroyPool(): void
    {
        foreach ($this->pool as $pid => $process) {
            Process::kill($pid, 0) && Process::kill($pid, SIGTERM);
        }
    }

    /**
     * This function should be called after sub-process forked.
     */
    protected function initWorker()
    {
        $pid = \getmypid();
        $processName = "aint-queue - {$this->name}:worker#{$pid} for {$this->queue->getChannel()}";
        $this->logger->info($processName.' is started.');
        SwooleHelper::setProcessName($processName);

        // required
        Runtime::enableCoroutine();
        // reset connection
        $this->queue->resetConnection();

        // register signal
        Process::signal(SIGUSR1, function () {
            $this->working = false;
            $this->workerReloadAble = true;
        });
        Process::signal(SIGUSR2, function () {
            $this->working = false;
            $this->workerReloadAble = false;
        });
    }

    /**
     * Run tasks in loop.
     *
     * @param Process $process
     */
    public function workStart(Process $process): void
    {
        $this->initWorker();

        Coroutine::create(function () use ($process) {
            Coroutine::defer(function () {
                $pid = \getmypid();
                $processName = "aint-queue - {$this->name}:worker#{$pid} for {$this->queue->getChannel()}";
                $this->logger->info($processName.' is stopped.');
                $this->queue->destroyConnection();
            });
            while ($this->working) {
                $messageId = $this->queue->popReady($this->name);
                if (!$messageId) {
                    Coroutine::sleep(1);
                    continue;
                }
                // If current worker is stopped,
                // the job popped will be push onto ready queue again.
                if (!$this->working) {
                    $this->queue->ready($messageId, $this->name, true);
                    break;
                }
                try {
                    $this->executeJob($messageId);
                } catch (\Throwable $t) {
                    $e = \get_class($t);
                    $this->logger->error("Job exec error,  {$e}: {$t->getMessage()}", [
                        'driver' => \get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $messageId,
                    ]);
                    if ($this->queue->isReserved($messageId)) {
                        $this->queue->release($messageId, 60);
                    }
                }

                $limit = $this->options['memory_limit'] ?? 96;
                if ($limit <= EnvironmentHelper::getCurrentMemoryUsage()) {
                    $this->logger->info("Memory exceeded, worker:{$this->name} will reload later.");
                    $this->working = false;
                    $this->workerReloadAble = true;
                }
            }
            if ($this->workerReloadAble) {
                $process->exit(1);
            }
        });
    }
}
