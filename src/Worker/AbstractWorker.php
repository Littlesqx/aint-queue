<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Logger\LoggerInterface;
use Littlesqx\AintQueue\QueueInterface;
use Swoole\Process;
use Swoole\Runtime;

abstract class AbstractWorker
{
    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var Process
     */
    protected $process;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var bool
     */
    protected $redirectStdinStdout = false;

    /**
     * @var int
     */
    protected $pipeType = 2;

    /**
     * @var bool
     */
    protected $enableCoroutine = true;

    /**
     * @var bool
     */
    protected $working = true;

    /**
     * @var bool
     */
    protected $workerReloadAble = true;

    /**
     * Worker constructor.
     *
     * @param QueueInterface  $queue
     * @param LoggerInterface $logger
     * @param array           $options
     */
    public function __construct(QueueInterface $queue, LoggerInterface $logger, array $options = [])
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->options = $options;
        $this->process = new Process([$this, 'work'], $this->redirectStdinStdout, $this->pipeType, $this->enableCoroutine);
    }

    /**
     * @return int
     */
    public function start(): int
    {
        $this->pid = $this->process->start();

        return $this->pid;
    }

    /**
     * Init, reset resource connection and register signal.
     *
     * @throws \Throwable
     */
    protected function init(): void
    {
        // required
        Runtime::enableCoroutine();
        // reset connection
        $this->queue->resetConnection();
        $this->logger->resetConnection();

        // register signal
        Process::signal(SIGUSR1, function () {
            $this->working = false;
            $this->workerReloadAble = true;
        });

        Process::signal(SIGUSR2, function () {
            $this->working = false;
            $this->workerReloadAble = false;
        });
    }

    /**
     * Get swoole process.
     *
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }

    /**
     * Working for handle job in loop.
     */
    abstract public function work(): void;
}
