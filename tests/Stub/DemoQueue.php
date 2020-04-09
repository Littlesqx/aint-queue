<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Stub;

use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\QueueInterface;

class DemoQueue implements QueueInterface
{
    /**
     * Get channel name of current queue.
     *
     * @return string
     */
    public function getChannel(): string
    {
        return 'demo';
    }

    /**
     * Get job message from queue.
     *
     * @param int $id
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function get(int $id): array
    {
        return [1, 2, 3];
    }

    /**
     * Get status of specific job.
     *
     * @param int $id
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function getStatus(int $id): int
    {
        return 0;
    }

    /**
     * Push an executable job message into queue.
     *
     * @param \Closure|JobInterface $message
     * @param int                   $delay
     *
     * @throws \Throwable
     */
    public function push($message, int $delay = 0): void
    {
        // TODO: Implement push() method.
    }

    /**
     * Pop a job message from waiting-queue.
     *
     * @return int|null
     *
     * @throws \Throwable
     */
    public function pop(): ?int
    {
        return null;
    }

    /**
     * Remove specific job from current queue.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function remove(int $id): void
    {
        // TODO: Implement remove() method.
    }

    /**
     * Release a job which was failed to execute.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function release(int $id, int $delay = 0): void
    {
        // TODO: Implement release() method.
    }

    /**
     * Fail a job.
     *
     * @param int         $id
     * @param string|null $payload
     *
     * @throws \Throwable
     */
    public function failed(int $id, string $payload = null): void
    {
        // TODO: Implement failed() method.
    }

    /**
     * Get all failed jobs.
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function getFailed(): array
    {
        return [];
    }

    /**
     * Clear failed job.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function clearFailed(int $id): void
    {
        // TODO: Implement clearFailed() method.
    }

    /**
     * Reload failed job.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function reloadFailed(int $id, int $delay = 0): void
    {
        // TODO: Implement reloadFailed() method.
    }

    /**
     * Clear current queue.
     *
     * @throws \Throwable
     */
    public function clear(): void
    {
        // TODO: Implement clear() method.
    }

    /**
     * Get status of current queue.
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function status(): array
    {
        return [];
    }

    /**
     * Reset connection.
     *
     * @throws \Throwable
     */
    public function initConnection(): void
    {
        // TODO: Implement resetConnection() method.
    }

    /**
     * Disconnect the connection.
     *
     * @throws \Throwable
     */
    public function destroyConnection(): void
    {
        // TODO: Implement destroyConnection() method.
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function isWaiting(int $id): bool
    {
        return false;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function isReserved(int $id): bool
    {
        return false;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function isDone(int $id): bool
    {
        return true;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function isFailed(int $id): bool
    {
        return false;
    }

    /**
     * Retry reserved job (only called when listener restart.).
     *
     * @throws \Throwable
     */
    public function retryReserved(): void
    {
        // TODO: Implement retryReserved() method.
    }

    /**
     * Moved the expired job to waiting queue.
     *
     * @throws \Throwable
     */
    public function migrateExpired(): void
    {
        // TODO: Implement migrateExpired() method.
    }
}
