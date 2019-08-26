<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/23.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Helper\SwooleHelper;
use Littlesqx\AintQueue\Manager;
use Swoole\Process as SwooleProcess;

class ProcessWorker extends AbstractWorker
{
    /**
     * @var bool
     */
    protected $canContinue = true;

    public function __construct(Manager $manager)
    {
        parent::__construct($manager, function () {

            SwooleHelper::setProcessName($this->getName());

            SwooleProcess::signal(SIGTERM, function () {
                $this->canContinue = false;
            });

            $this->initRedis();

            while ($this->canContinue) {
                $messageId = $this->redis->brpop([$this->getTaskQueueName()], 0)[1] ?? 0;
                echo "exec $messageId start\n";
                $this->manager->executeJobInSubProcess($messageId);
                echo "exec $messageId end\n";
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
        return 'aint-queue-process-worker' . ":{$this->topic}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-process-worker:task-queue' . ":{$this->topic}";
    }
}
