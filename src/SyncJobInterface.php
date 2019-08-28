<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

interface SyncJobInterface extends JobInterface
{
    /**
     * Get current job's max execution time(seconds).
     *
     * @return int
     */
    public function getTtr(): int;
}
