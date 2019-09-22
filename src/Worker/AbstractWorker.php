<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Connection\Pool\RedisPool;
use Littlesqx\AintQueue\Connection\PoolFactory;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Manager;
use Swoole\Process as SwooleProcess;

abstract class AbstractWorker implements WorkerInterface
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var SwooleProcess
     */
    protected $process;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var bool
     */
    protected $canContinue = true;

    public function __construct(Manager $manager, \Closure $closure, bool $enableCoroutine = false)
    {
        $this->manager = $manager;
        $this->channel = $manager->getQueue()->getChannel();

        SwooleProcess::signal(SIGCHLD, function () {
            while ($ret = SwooleProcess::wait(false)) {
                $this->manager->getLogger()->info("Worker: {$this->getName()} - pid={$ret['pid']} exit.");
            }
        });

        $this->process = new SwooleProcess($closure, false, 1, $enableCoroutine);
    }

    public function resetConnectionPool()
    {
        $this->manager->getQueue()->resetConnectionPool();
    }

    /**
     * Start current worker.
     *
     * @return bool
     *
     * @throws RuntimeException
     */
    public function start(): bool
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Worker is running, do not start again!');
        }

        $this->pid = $this->process->start();

        $this->manager->getLogger()->info($this->getName().' - pid='.$this->pid.' start.');

        return $this->isRunning();
    }

    /**
     * Stop current worker.
     *
     * @throws RuntimeException
     */
    public function stop(): void
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Worker is already stop!');
        }
        SwooleProcess::kill($this->pid, SIGTERM);
    }

    /**
     * Wait worker stop and exit.
     *
     * @return bool
     *
     * @throws RuntimeException
     */
    public function wait(): bool
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Worker is already stop!');
        }

        SwooleProcess::kill($this->pid, SIGUSR2);

        return true;
    }

    /**
     * Receive an task into current worker.
     *
     * @param int $messageId
     */
    public function receive($messageId): void
    {
        $this->manager->getQueue()->ready($this->getName(), $messageId);
    }

    /**
     * Whether current worker is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->pid > 0 && SwooleProcess::kill($this->pid, 0);
    }
}
