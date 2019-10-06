<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

interface WorkerInterface
{
    /**
     * Whether current worker is running.
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Receive a task onto current worker.
     *
     * @param int $messageId
     */
    public function receive($messageId): void;

    /**
     * Start current worker.
     *
     * @return int
     */
    public function start(): int;

    /**
     * Stop current worker.
     */
    public function stop(): void;

    /**
     * Wait current worker.
     */
    public function wait(): void;

    /**
     * Run tasks in loop.
     */
    public function work(): void;
}
