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
use Predis\Client;

class RedisConnector implements Connector
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var Client
     */
    private $connector;

    /**
     * RedisConnector constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    // Make clone dis-accessible
    private function __clone()
    {
    }

    /**
     * @param array $options
     *
     * @return RedisConnector
     */
    public static function create(array $options)
    {
        $connector = new static($options);
        $connector->connect();

        return $connector;
    }

    /**
     * Make current connector instance connected.
     */
    public function connect(): void
    {
        $this->connector = new Client($this->options);
    }

    /**
     * Whether current connector is connected.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connector && ($this->connector->isConnected() || $this->connector->ping());
    }

    /**
     * Make current connector instance disconnected.
     */
    public function disConnect(): void
    {
        $this->connector && $this->connector->disconnect();
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws ConnectorException
     */
    public function __call($name, $arguments)
    {
        if (!$this->isConnected()) {
            throw new ConnectorException('Connector is not connected');
        }

        return $this->connector->{$name}(...$arguments);
    }
}
