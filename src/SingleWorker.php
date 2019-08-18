<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/16.
 */

namespace Littlesqx\AintQueue;

use Symfony\Component\Process\Process;

class SingleWorker implements WorkerInterface
{
    /**
     * deliver an task into current worker.
     *
     * @param \Closure|JobInterface $task
     *
     * @return mixed
     */
    public function deliver($task)
    {
        $process = new Process([]);
        // set timeout
        if ($task instanceof JobInterface && ($ttr = $task->getTtr()) > 0) {
            $process->setTimeout($ttr);
        }

        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });
    }
}