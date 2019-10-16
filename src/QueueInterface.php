<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue;

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
     * @return array
     * @throws \Throwable
     */
    public function get(int $id): array;

    /**
     * Get status of specific job.
     *
     * @param int $id
     *
     * @return int
     * @throws \Throwable
     */
    public function getStatus(int $id): int;

    /**
     * Push an executable job message into queue.
     *
     * @param \Closure|JobInterface $message
     * @param int                   $delay
     *
     * @throws \Throwable
     */
    public function push($message, int $delay = 0): void;

    /**
     * Pop a job message from waiting-queue.
     *
     * @return int|null
     * @throws \Throwable
     */
    public function pop(): ?int;

    /**
     * Remove specific job from current queue.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function remove(int $id): void;

    /**
     * Release a job which was failed to execute.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function release(int $id, int $delay = 0): void;

    /**
     * Fail a job.
     *
     * @param int $id
     * @param string|null $payload
     *
     * @throws \Throwable
     */
    public function failed(int $id, string $payload = null): void;

    /**
     * Get all failed jobs.
     *
     * @return array
     * @throws \Throwable
     */
    public function getFailed(): array;

    /**
     * Clear failed job.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function clearFailed(int $id): void;

    /**
     * Reload failed job.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function reloadFailed(int $id, int $delay = 0): void;

    /**
     * Clear current queue.
     *
     * @throws \Throwable
     */
    public function clear(): void;

    /**
     * Get status of current queue.
     *
     * @return array
     * @throws \Throwable
     */
    public function status(): array;

    /**
     * Reset connection.
     *
     * @throws \Throwable
     */
    public function resetConnection(): void;

    /**
     * Disconnect the connection.
     *
     * @throws \Throwable
     */
    public function destroyConnection(): void;

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws \Throwable
     */
    public function isWaiting(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws \Throwable
     */
    public function isReserved(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws \Throwable
     */
    public function isDone(int $id): bool;

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws \Throwable
     */
    public function isFailed(int $id): bool;

    /**
     * Retry reserved job (only called when listener restart.).
     *
     * @throws \Throwable
     */
    public function retryReserved(): void;

    /**
     * Moved the expired job to waiting queue.
     *
     * @throws \Throwable
     */
    public function migrateExpired(): void;
}
