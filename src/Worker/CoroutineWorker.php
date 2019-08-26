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

class CoroutineWorker extends AbstractWorker
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $topic;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        $this->topic = $manager->getQueue()->getTopic();

        parent::__construct($manager, function () {

            SwooleHelper::setProcessName($this->getName());

        }, true);
    }

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'aint-queue-coroutine-worker' . ":{$this->topic}";
    }

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string
    {
        return 'aint-queue-coroutine-worker:task-queue' . ":{$this->topic}";
    }

}
