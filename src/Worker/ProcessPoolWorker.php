<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Helper\SwooleHelper;
use Littlesqx\AintQueue\Manager;
use Predis\Client;
use Swoole\Process as SwooleProcess;
use Swoole\Process\Pool as SwooleProcessPool;

class ProcessPoolWorker extends AbstractWorker
{
    /**
     * @var SwooleProcess[]
     */
    protected $process = [];

    /**
     * @var SwooleProcessPool
     */
    protected $processPool;

    /**
     * @var Client
     */
    protected $redis;

    /**
     * @var bool
     */
    protected $canContinue = true;

    /**
     * @var Manager
     */
    protected $manager;

    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            SwooleHelper::setProcessName($this->getName());

            $this->initRedis();

            $this->processPool = new SwooleProcessPool(4);
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
     */
    protected function workerStart(SwooleProcessPool $pool, $workerId)
    {
        $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' start.');

        SwooleHelper::setProcessName($this->getName().' sub-worker:'.$workerId);

        SwooleProcess::signal(SIGTERM, function () {
            $this->canContinue = false;
        });

        $this->initRedis();

        while ($this->canContinue) {
            $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
            $this->manager->executeJob($messageId);
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
        $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' stop.');
    }

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'aint-queue-process-pool-worker'.":{$this->topic}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-process-pool-worker:task-queue'.":{$this->topic}";
    }
}
