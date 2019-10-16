<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue\Connection;

interface PoolInterface
{
    /**
     * Get a connection from current pool.
     *
     * @return mixed
     */
    public function get();

    /**
     * Release a connection back to current pool.
     *
     * @param $connection
     */
    public function release($connection): void;

    /**
     * Close and clear current pool.
     */
    public function flush(): void;
}
