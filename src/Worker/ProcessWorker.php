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
use Swoole\Runtime;

class ProcessWorker extends AbstractWorker
{
    protected $timer = 0;
    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {

            Runtime::enableCoroutine(true);

            SwooleHelper::setProcessName($this->getTaskQueueName());

            SwooleProcess::signal(SIGTERM, function () {
                $this->canContinue = false;
                $this->manager->getLogger()->info("Worker: {$this->getName()} receive signal SIGTERM.");

            });
            SwooleProcess::signal(SIGUSR2, function () {
                $this->canContinue = false;
                $this->manager->getLogger()->info("Worker: {$this->getName()} receive signal SIGUSR2.");
                $this->atomic->wakeup();
            });

            $this->initRedis();

            Coroutine::create(function () {
                while ($this->canContinue) {
                    $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
                    $this->manager->executeJobInProcess($messageId);
                    if (!$this->canContinue) {
                        $this->manager->getLogger()->info($this->getName().' - pid='.getmypid().' pre-stop.');
                    }
                }
            });

        }, true);
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

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-process-worker:task-queue'.":{$this->channel}";
    }
}
