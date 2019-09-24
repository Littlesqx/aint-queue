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
use Littlesqx\AintQueue\QueueInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process\Pool as SwooleProcessPool;

class ProcessPoolWorker extends AbstractWorker
{
    /**
     * @var SwooleProcessPool
     */
    protected $processPool;

    public function __construct(array $options, LoggerInterface $logger, QueueInterface $queue)
    {
        parent::__construct($options, $logger, $queue, function () {
            SwooleHelper::setProcessName($this->getName());

            $workerNum = $this->options['worker_number'] ?? 4;
            $this->processPool = new SwooleProcessPool($workerNum);
            $this->processPool->on('WorkerStart', function ($pool, $workerId) {
                $this->workerStart($pool, $workerId);
            });
            $this->processPool->on('WorkerStop', function ($pool, $workerId) {
                $this->workerStop($pool, $workerId);
            });

            $this->processPool->start();
        });
    }

    /**
     * Worker start event callback.
     *
     * @param SwooleProcessPool $pool
     * @param $workerId
     * @throws \Throwable
     */
    protected function workerStart(SwooleProcessPool $pool, $workerId)
    {
        $this->queue->resetConnection();

        $this->logger->info($this->getName().' sub-worker:'.$workerId.' start.');

        SwooleHelper::setProcessName($this->getName().' sub-worker:'.$workerId);

        while ($this->canContinue) {
            $messageId = $this->queue->popReady($this->getName());
            $this->executeJob($messageId);
            $limit = $this->options['memory_limit'] ?? 512;
            if (!$this->canContinue && $limit > EnvironmentHelper::getCurrentMemoryUsage()) {
                $this->logger->info($this->getName().' sub-worker:'.$workerId.' stop.');
            }
        }
    }

    /**
     * Worker stop event callback.
     *
     * @param SwooleProcessPool $pool
     * @param $workerId
     */
    protected function workerStop(SwooleProcessPool $pool, $workerId)
    {
        $this->logger->info($this->getName().' sub-worker:'.$workerId.' stop.');
    }

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'aint-queue-process-pool-worker'.":{$this->queue->getChannel()}";
    }
}
