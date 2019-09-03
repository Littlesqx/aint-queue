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

class ProcessWorker extends AbstractWorker
{
    protected $timer = 0;

    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {
            $this->resetConnectionPool();

            SwooleHelper::setProcessName($this->getName());

            while ($this->canContinue) {
                $messageId = $this->manager->getQueue()->getReady($this->getName());
                $this->manager->executeJobInProcess($messageId);
                if (!$this->canContinue) {
                    $this->manager->getLogger()->info($this->getName().' - pid='.getmypid().' pre-stop.');
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
        return 'aint-queue-process-worker'.":{$this->channel}";
    }
}
