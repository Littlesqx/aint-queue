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

    /**
     * @var bool
     */
    protected $canContinue = true;

    public function __construct(Manager $manager, \Closure $closure, $enableCoroutine = null)
    {
        $this->manager = $manager;

        $this->topic = $manager->getQueue()->getTopic();

        // set process name
        SwooleHelper::setProcessName($this->getName());

        $this->initRedis();

        $this->manager->getLogger()->info($this->getName().' start.');

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
