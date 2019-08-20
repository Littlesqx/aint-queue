<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/19.
 */

namespace Littlesqx\AintQueue\Timer;

use Swoole\Process;

class TimerProcess
{
    /**
     * @var int
     */
    protected $pid;

    /**
     * @var Process
     */
    protected $process;

    /**
     * @var TickTimerInterface[]
     */
    protected $timers = [];

    /**
     * TimerProcess constructor.
     *
     * @param TickTimerInterface[] $timers
     */
    public function __construct(array $timers)
    {
        $this->process = new Process(function () use ($timers) {
            foreach ($timers as $timer) {
                $timer->start();
                $this->timers[] = $timer;
            }
        });
    }

    public function start()
    {
        $this->pid = $this->process->start();

        return $this->pid;
    }

    public function clearAll()
    {
        if (null === $this->pid) {
            return false;
        }

        foreach ($this->timers as $timer) {
            $timer->clear();
        }

        return true;
    }

    public function quit()
    {
        $this->clearAll();
        $this->process->exit();
    }

    public function getTimers(): array
    {
        return $this->timers;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

}
