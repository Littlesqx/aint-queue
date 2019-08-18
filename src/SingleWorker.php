<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
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
            if (Process::ERR === $type) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });
    }
}
