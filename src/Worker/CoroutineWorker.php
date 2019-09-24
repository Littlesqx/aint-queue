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
use Littlesqx\AintQueue\QueueInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Runtime;

class CoroutineWorker extends AbstractWorker
{
    public function __construct(array $options, LoggerInterface $logger, QueueInterface $queue)
    {
        parent::__construct($options, $logger, $queue, function () {
            $this->queue->resetConnection();
            // required
            Runtime::enableCoroutine();

            SwooleHelper::setProcessName($this->getName());

            while ($this->canContinue) {
                $messageId = $this->queue->popReady($this->getName());
                Coroutine::create([$this, 'executeJob'], $messageId);
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
        return 'aint-queue-coroutine-worker'.":{$this->queue->getChannel()}";
    }
}
