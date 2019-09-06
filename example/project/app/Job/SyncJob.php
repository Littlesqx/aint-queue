<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/06.
 */

namespace App\Job;

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
     */
    public function handle(QueueInterface $queue)
    {
        $int = random_int(1, 5);
        echo "sync job sleep#{$int} begin \n";
        sleep($int);
        echo "sync job sleep#{$int} end \n";
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
        return true;
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
        return time() + 60;
    }

    /**
     * Get current job's max execution time(seconds).
     *
     * @return int
     */
    public function getTtr(): int
    {
        return 60;
    }
}