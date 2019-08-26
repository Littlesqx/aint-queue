<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/19.
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
        parent::__construct(function () use ($timers){

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
