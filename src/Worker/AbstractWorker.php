<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Manager;
use Predis\Client;
use Swoole\Atomic;
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
     * @var Client
     */
    protected $redis;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var bool
     */
    protected $canContinue = true;

    protected $atomic;

    public function __construct(Manager $manager, \Closure $closure, bool $enableCoroutine = false)
    {
        $this->manager = $manager;
        $this->channel = $manager->getQueue()->getChannel();

        $this->initRedis();

        SwooleProcess::signal(SIGCHLD, function () {
            while ($ret = SwooleProcess::wait(false)) {
                $this->manager->getLogger()->info("Worker: {$this->getName()} - pid={$ret['pid']} exit.");
            }
        });

        $this->atomic = new Atomic();

        $this->process = new SwooleProcess($closure, false, 1, $enableCoroutine);
    }

    /**
     * Init redis connection.
     */
    protected function initRedis(): void
    {
        $this->redis = new Client(['read_write_timeout' => 0]);
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

        return false !== $this->atomic->wait(2);
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
