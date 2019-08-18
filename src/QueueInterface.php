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
     * @param $message
     *
     * @return mixed
     */
    public function push($message);

    /**
     * Pop an job message from queue.
     *
     * @return mixed
     */
    public function pop();

    /**
     * Remove specific job from current queue.
     *
     * @param $id
     *
     * @return mixed
     */
    public function remove($id);

    /**
     * Get status of specific job.
     *
     * @param $id
     *
     * @return mixed
     */
    public function status($id);

    /**
     * CLear current queue,.
     *
     * @return mixed
     */
    public function clear();
}
