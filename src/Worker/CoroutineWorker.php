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
use Swoole\Runtime;

class CoroutineWorker extends AbstractWorker
{
    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            $this->resetConnectionPool();
            // required
            Runtime::enableCoroutine();

            SwooleHelper::setProcessName($this->getName());

            while ($this->canContinue) {
                $messageId = $this->manager->getQueue()->getReady($this->getName());
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

}
