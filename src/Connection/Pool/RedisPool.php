<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Connection\Pool;

use Littlesqx\AintQueue\Connection\AbstractPool;
use Predis\Client;

class RedisPool extends AbstractPool
{
    /**
     * Create a connection.
     *
     * @return Client
     */
    public function createConnection(): Client
    {
        return new Client($this->options['connection'] ?? []);
    }

    /**
     * Close a connection.
     *
     * @param Client $connection
     */
    public function closeConnection($connection): void
    {
        $connection->getConnection()->disconnect();
    }

    /**
     * Check connect Whether is available.
     *
     * @param Client $connection
     *
     * @return bool
     */
    public function checkConnection($connection): bool
    {
        try {
            return $connection->isConnected() && $connection->ping();
        } catch (\Exception $e) {}

        return false;
    }
}
