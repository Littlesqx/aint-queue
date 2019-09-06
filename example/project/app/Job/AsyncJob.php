<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/06.
 */

namespace App\Job;

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
        $int = random_int(1, 5);
        echo "async job sleep#{$int} begin \n";
        sleep($int);
        echo "async job sleep#{$int} end \n";
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
        return time();
    }
}