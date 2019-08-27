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
use Swoole\Coroutine;

class CoJob implements CoJobInterface
{
    /**
     * Execute current job.
     *
     * @param QueueInterface $queue
     *
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function handle(QueueInterface $queue)
    {
        $int = random_int(1, 10);
        echo "coroutine job sleep#{$int} begin \n";
        Coroutine::sleep($int);
        echo "coroutine job sleep#{$int} end \n";
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
