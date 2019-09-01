<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Worker\CoroutineWorker;
use Littlesqx\AintQueue\Worker\ProcessPoolWorker;
use Littlesqx\AintQueue\Worker\ProcessWorker;

class WorkerDirector
{
    /**
     * @var ProcessWorker
     */
    protected $processWorker;

    /**
     * @var ProcessPoolWorker
     */
    protected $processPoolWorker;

    /**
     * @var CoroutineWorker
     */
    protected $coroutineWorker;

    public function __construct(Manager $manager)
    {
        $options = $manager->getOptions();

        if ($options['worker']['process_worker']['enable'] ?? false) {
            $this->processWorker = new ProcessWorker($manager);
            $this->processWorker->start();
        }

        if ($options['worker']['process_pool_worker']['enable'] ?? false) {
            $this->processPoolWorker = new ProcessPoolWorker($manager);
            $this->processPoolWorker->start();
        }

        if ($options['worker']['coroutine_worker']['enable'] ?? false) {
            $this->coroutineWorker = new CoroutineWorker($manager);
            $this->coroutineWorker->start();
        }
    }

    /**
     * Dispatch job to executor.
     *
     * @param $messageId
     * @param $message*
     */
    public function dispatch($messageId, $message): void
    {
        if ($message instanceof CoJobInterface) {
            $this->coroutineWorker->receive($messageId);
        } elseif ($message instanceof AsyncJobInterface) {
            $this->processPoolWorker->receive($messageId);
        } else {
            $this->processWorker->receive($messageId);
        }
    }

    /**
     * Wait running worker exit.
     *
     * @throws Exception\RuntimeException
     */
    public function wait()
    {
        if ($this->processWorker && $this->processWorker->isRunning()) {
            $this->processWorker->wait();
        }

        if ($this->processPoolWorker && $this->processPoolWorker->isRunning()) {
            $this->processPoolWorker->wait();
        }
    }

    /**
     * Stop running worker.
     *
     * @throws Exception\RuntimeException
     */
    public function stop()
    {
        if ($this->processWorker->isRunning()) {
            $this->processWorker->stop();
        }

        if ($this->processPoolWorker && $this->processPoolWorker->isRunning()) {
            $this->processPoolWorker->stop();
        }
    }
}
