<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue;

interface JobSnapshotterInterface
{
    /**
     * @param array $snapshot
     */
    public function handle(array $snapshot): void;
}
