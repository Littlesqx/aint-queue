<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Exception\InvalidArgumentException;

interface QueueInterface
{
    /**
     * Get channel name of current queue.
     *
     * @return string
     */
    public function getChannel(): string;

    /**
     * Get job message from queue.
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function get($id);

    /**
     * Get status of specific job.
     *
     * @param $id
     *
     * @return mixed
     */
    public function getStatus($id);

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
     * Release a job which was failed to execute.
     *
     * @param $id
     * @param int $delay
     */
    public function release($id, int $delay = 0);

    /**
     * Get current queue's size.
     *
     * @return int
     */
    public function size(): int;

    /**
     * Clear current queue.
     *
     * @return mixed
     */
    public function clear();

    /**
     * Get status of current queue.
     *
     * @return array
     */
    public function status(): array;
}
