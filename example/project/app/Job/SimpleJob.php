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

class SimpleJob implements JobInterface
{
    /**
     * Execute current job.
     *
     * @return mixed
     */
    public function handle(): void
    {
        echo "Hello World\n";
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
