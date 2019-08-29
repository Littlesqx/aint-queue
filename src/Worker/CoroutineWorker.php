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
use Swoole\Coroutine;
use Swoole\Runtime;

class CoroutineWorker extends AbstractWorker
{
    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            // required
            Runtime::enableCoroutine(true);

            $this->initRedis();

            while ($this->canContinue) {
                $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
                Coroutine::create([$this->manager, 'executeJob'], $messageId);
            }
        }, true);
    }

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'aint-queue-coroutine-worker'.":{$this->channel}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-coroutine-worker:task-queue'.":{$this->channel}";
    }
}
