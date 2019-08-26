<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Timer;

use Littlesqx\AintQueue\Helper\SwooleHelper;
use Swoole\Process;

class TickTimerProcess extends Process
{
    /**
     * @var TickTimer[]
     */
    protected $timers;

    public function __construct(array $timers)
    {
        parent::__construct(function () use ($timers) {
            SwooleHelper::setProcessName('aint-queue - timer process');

            $this->timers = $timers;

            foreach ($this->timers as $timer) {
                $timer->start();
            }

            Process::signal(SIGUSR1, function () {
                foreach ($this->timers as $timer) {
                    $timer->clear();
                }
                $this->exit(0);
            });
        });
    }

    /**
     * Stop the timerProcess.
     */
    public function stop()
    {
        return self::kill($this->pid, SIGUSR1);
    }
}
