<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/26.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Manager;
use Predis\Client;
use Swoole\Process as SwooleProcess;

abstract class AbstractWorker extends SwooleProcess implements WorkerInterface
{

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var Client
     */
    protected $redis;

    /**
     * @var string
     */
    protected $topic;

    public function __construct(Manager $manager, \Closure $closure, $enableCoroutine = null)
    {
        $this->manager = $manager;

        $this->topic = $manager->getQueue()->getTopic();

        $this->initRedis();

        parent::__construct($closure, null, null, $enableCoroutine);
    }

    /**
     * Init redis connection.
     */
    protected function initRedis(): void
    {
        $this->redis = new Client(['read_write_timeout' => 0]);
    }

    /**
     * Receive an task into current worker.
     *
     * @param int $messageId
     */
    public function receive($messageId): void
    {
        $this->redis->lpush($this->getTaskQueueName(), [$messageId]);
    }

}