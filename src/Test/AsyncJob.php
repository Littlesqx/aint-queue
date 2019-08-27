<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Test;

use Littlesqx\AintQueue\AsyncJobInterface;
use Littlesqx\AintQueue\QueueInterface;

class AsyncJob implements AsyncJobInterface
{
    /**
     * Execute current job.
     *
     * @param QueueInterface $queue
     *
     * @return mixed
     */
    public function handle(QueueInterface $queue)
    {
        // TODO: Implement handle() method.
    }

    /**
     * Get current job's max execution time(seconds).
     *
     * @return int
     */
    public function getTtr(): int
    {
        // TODO: Implement getTtr() method.
    }

    /**
     * Determine whether current job can retry if fail.
     *
     * @param int $attempt
     * @param $error
     *
     * @return bool
     */
    public function canRetry(int $attempt, $error): bool
    {
        // TODO: Implement canRetry() method.
    }
}
