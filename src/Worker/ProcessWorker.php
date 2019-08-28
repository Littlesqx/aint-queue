<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Manager;
use Swoole\Process as SwooleProcess;

class ProcessWorker extends AbstractWorker
{
    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            SwooleProcess::signal(SIGTERM, function () {
                $this->canContinue = false;
            });

            $this->initRedis();

            while ($this->canContinue) {
                $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
                $this->manager->executeJobInProcess($messageId);
            }
        });
    }

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'aint-queue-process-worker'.":{$this->topic}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-process-worker:task-queue'.":{$this->topic}";
    }
}
