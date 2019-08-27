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
     *
     * @throws \Exception
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
}
