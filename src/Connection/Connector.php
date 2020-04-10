<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Connection;

use Littlesqx\AintQueue\Exception\ConnectorException;

interface Connector
{
    /**
     * Make current connector instance connected.
     *
     * @throws ConnectorException
     */
    public function connect(): void;

    /**
     * Whether current connector is connected.
     *
     * @return bool
     *
     * @throws ConnectorException
     */
    public function isConnected(): bool;

    /**
     * Make current connector instance disconnected.
     *
     * @throws ConnectorException
     */
    public function disConnect(): void;
}
