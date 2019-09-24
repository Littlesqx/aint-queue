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

class ProcessWorker extends AbstractWorker
{
    public function __construct(array $options, LoggerInterface $logger, QueueInterface $queue)
    {
        parent::__construct($options, $logger, $queue, function () {
            $this->queue->resetConnection();
            SwooleHelper::setProcessName($this->getName());

            while ($this->canContinue) {
                $messageId = $this->queue->popReady($this->getName());
                $this->executeJobInProcess($messageId);
                if (!$this->canContinue) {
                    $this->logger->info($this->getName().' - pid='.getmypid().' pre-stop.');
                }
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
        return 'aint-queue-process-worker'.":{$this->queue->getChannel()}";
    }
}
