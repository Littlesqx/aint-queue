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
     * Run an task on current worker.
     *
     * @param int $messageId
     */
    public function receive($messageId): void;

    /**
     * Start current worker.
     *
     * @return bool
     */
    public function start(): bool;

    /**
     * Stop current worker.
     */
    public function stop(): void;

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string;

}
