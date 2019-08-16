<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

interface QueueInterface
{
    /**
     * Push an executable job message into queue.
     *
     * @param $job
     *
     * @return mixed
     */
    public function push($job);

    /**
     * Pop an job message from queue.
     *
     * @return mixed
     */
    public function pop();
}
