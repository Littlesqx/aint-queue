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
use Swoole\Coroutine;
use Swoole\Process as SwooleProcess;
use Swoole\Process\Pool as SwooleProcessPool;
use Swoole\Runtime;

class ProcessPoolWorker extends AbstractWorker
{
    /**
     * @var SwooleProcessPool
     */
    protected $processPool;

    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            SwooleHelper::setProcessName($this->getTaskQueueName());

            $this->initRedis();

            $this->processPool = new SwooleProcessPool(4, 0, 0, true);
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
        Runtime::enableCoroutine(true);

        $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' start.');

        SwooleHelper::setProcessName($this->getName().' sub-worker:'.$workerId);

        SwooleProcess::signal(SIGTERM, function () use ($workerId) {
            $this->canContinue = false;
            $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' '.'receive signal SIGTERM.');
        });

        SwooleProcess::signal(SIGQUIT, function () use ($workerId) {
            $this->canContinue = false;
            $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' '.'receive signal SIGTERM.');
        });

        SwooleProcess::signal(SIGKILL, function () use ($workerId) {
            $this->canContinue = false;
            $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' '.'receive signal SIGTERM.');
        });

        $this->initRedis();

        Coroutine::create(function () use ($workerId) {
            while ($this->canContinue) {
                $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
                $this->manager->getLogger()->info('start '.$messageId.'.');
                $this->manager->executeJob($messageId);
                $this->manager->getLogger()->info('end '.$messageId.'.');
                if (!$this->canContinue) {
                    $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' start.');
                }
            }
        });
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
        return 'aint-queue-process-pool-worker'.":{$this->channel}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-process-pool-worker:task-queue'.":{$this->channel}";
    }
}
