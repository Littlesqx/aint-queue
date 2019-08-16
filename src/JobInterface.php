<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

interface JobInterface
{
    /**
     * Execute current job.
     *
     * @param QueueInterface $queue
     *
     * @return mixed
     */
    public function handle(QueueInterface $queue);

    /**
     * Get current job's max execution time.
     *
     * @return int
     */
    public function getTtr(): int;

    /**
     * Determine whether current job can retry if fail.
     *
     * @param int $attempt
     * @param $error
     *
     * @return mixed
     */
    public function canRetry(int $attempt, $error);
}
