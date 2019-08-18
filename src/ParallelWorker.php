<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/16.
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