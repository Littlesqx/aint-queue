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
use Psr\Log\LoggerInterface;

class WorkerDirector
{
    /**
     * @var array
     */
    protected $options;

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

    /**
     * WorkerDirector constructor.
     *
     * @param array           $workerOptions
     * @param LoggerInterface $logger
     * @param QueueInterface  $queue
     *
     * @throws Exception\RuntimeException
     */
    public function __construct(array $workerOptions, LoggerInterface $logger, QueueInterface $queue)
    {
        $this->options = $workerOptions;

        if ($workerOptions['process_worker']['enable'] ?? false) {
            $this->processWorker = new ProcessWorker($workerOptions['process_worker'] ?? [], $logger, $queue);
            $this->processWorker->start();
        }

        if ($workerOptions['process_pool_worker']['enable'] ?? false) {
            $this->processPoolWorker = new ProcessPoolWorker($workerOptions['process_pool_worker'] ?? [], $logger, $queue);
            $this->processPoolWorker->start();
        }

        if ($workerOptions['coroutine_worker']['enable'] ?? false) {
            $this->coroutineWorker = new CoroutineWorker($workerOptions['coroutine_worker'] ?? [], $logger, $queue);
            $this->coroutineWorker->start();
        }
    }

    /**
     * Dispatch job to executor.
     *
     * @param $messageId
     * @param $message
     *
     * @throws \Throwable
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
