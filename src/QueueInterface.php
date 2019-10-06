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
use Littlesqx\AintQueue\Exception\RuntimeException;

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
     * Pop a job message from waiting-queue.
     *
     * @return mixed
     */
    public function pop();

    /**
     * Pop a job message from ready-queue.
     *
     * @param string $worker
     *
     * @return mixed
     */
    public function popReady(string $worker);

    /**
     * Remove specific job from current queue.
     *
     * @param $id
     *
     * @return mixed
     */
    public function remove($id);

    /**
     * Ready a job onto worker queue.
     *
     * @param $id
     * @param string $worker
     * @param bool   $ontoFront
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function ready($id, string $worker, bool $ontoFront = false);

    /**
     * Release a job which was failed to execute.
     *
     * @param $id
     * @param int $delay
     */
    public function release($id, int $delay = 0);

    /**
     * Fail a job.
     *
     * @param $id
     * @param string|null $payload
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function failed($id, $payload = null);

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

    /**
     * Reset connection.
     */
    public function resetConnection(): void;

    /**
     * Disconnect the connection.
     */
    public function destroyConnection(): void;

    /**
     * Delay to execute the job.
     *
     * @param int $delay
     *
     * @return $this
     */
    public function delay(int $delay);

    /**
     * @param int $id of a job message
     *
     * @return bool
     */
    public function isWaiting(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     */
    public function isReserved(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     */
    public function isDone(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     */
    public function isFailed(int $id): bool;
}
