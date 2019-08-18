<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

class ParallelWorker implements WorkerInterface
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
        // TODO: Implement deliver() method.
    }
}
