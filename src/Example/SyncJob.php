<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Example;

use Littlesqx\AintQueue\QueueInterface;
use Littlesqx\AintQueue\SyncJobInterface;

class SyncJob implements SyncJobInterface
{
    /**
     * Execute current job.
     *
     * @param QueueInterface $queue
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function handle(QueueInterface $queue)
    {
        $int = random_int(1, 5);
        echo "sync job sleep#{$int} begin \n";
        sleep($int);
        echo "sync job sleep#{$int} end \n";
    }

    /**
     * Get current job's max execution time(seconds).
     *
     * @return int
     */
    public function getTtr(): int
    {
        return 30;
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
        return false;
    }

    /**
     * Get current job's next execution unix time after failed.
     *
     * @param int $attempt
     *
     * @return int
     */
    public function getNextRetryTime(int $attempt): int
    {
        return time() + 60 * $attempt;
    }
}
