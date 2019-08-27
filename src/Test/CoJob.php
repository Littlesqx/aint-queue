<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Test;

use Littlesqx\AintQueue\CoJobInterface;
use Littlesqx\AintQueue\QueueInterface;

class CoJob implements CoJobInterface
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
        $int = random_int(1, 10);
        echo "coroutine sleep#{$int} begin \n";
        \Swoole\Coroutine::sleep($int);
        echo "coroutine sleep#{$int} end \n";
    }

    /**
     * Get current job's max execution time(seconds).
     *
     * @return int
     */
    public function getTtr(): int
    {
        return 0;
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
