<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace App\Event;

use Littlesqx\AintQueue\JobSnapshotterInterface;

class ExampleEvent implements JobSnapshotterInterface
{
    /**
     * @param array $snapshot
     */
    public function handle(array $snapshot): void
    {
        // TODO: implements handle()
        // report the status by mail
        // or record the queue status
    }
}
