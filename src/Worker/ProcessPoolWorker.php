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
use Swoole\Process\Pool as SwooleProcessPool;

class ProcessPoolWorker extends AbstractWorker
{
    /**
     * @var SwooleProcessPool
     */
    protected $processPool;

    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            SwooleHelper::setProcessName($this->getName());

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
        $this->resetConnectionPool();

        $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' start.');

        SwooleHelper::setProcessName($this->getName().' sub-worker:'.$workerId);

        while ($this->canContinue) {
            $messageId = $this->manager->getQueue()->getReady($this->getName());
            $this->manager->executeJob($messageId);
            if (!$this->canContinue) {
                $this->manager->getLogger()->info($this->getName().' sub-worker:'.$workerId.' start.');
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
}
