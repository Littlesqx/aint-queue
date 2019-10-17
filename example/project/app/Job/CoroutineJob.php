<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace App\Job;

use Littlesqx\AintQueue\JobInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

class CoroutineJob implements JobInterface
{
    /**
     * Execute current job.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $wg = new WaitGroup();
        $wg->add(5);
        $result = [];
        $begin = time();
        Coroutine::create(function () use (&$result, $wg) {
            Coroutine::sleep(1);
            $result[0] = 'a';
            $wg->done();
        });
        Coroutine::create(function () use (&$result, $wg) {
            Coroutine::sleep(1);
            $result[1] = 'b';
            $wg->done();
        });
        Coroutine::create(function () use (&$result, $wg) {
            Coroutine::sleep(1);
            $result[2] = 'c';
            $wg->done();
        });
        Coroutine::create(function () use (&$result, $wg) {
            $result[3] = 'd';
            Coroutine::sleep(1);
            $wg->done();
        });
        Coroutine::create(function () use (&$result, $wg) {
            $result[4] = 'e';
            Coroutine::sleep(1);
            $wg->done();
        });
        $wg->wait(2);
        echo 'took ', time() - $begin, ' seconds', "\n";
        var_dump($result);
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
        return $attempt <= 5;
    }

    /**
     * Get current job's next execution unix time after failed.
     *
     * @param int $attempt
     *
     * @return int
     */
    public function getRetryTime(int $attempt): int
    {
        return 0;
    }

    /**
     * After failed, this function will be called.
     *
     * @param int   $id
     * @param array $payload
     */
    public function failed(int $id, array $payload): void
    {
        echo "job#{$id} was failed.\n";
    }
}
