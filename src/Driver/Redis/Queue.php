<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Driver\Redis;

use Littlesqx\AintQueue\AbstractQueue;
use Littlesqx\AintQueue\Connection\Pool\RedisPool;
use Littlesqx\AintQueue\Connection\PoolFactory;
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\Serializer\Factory;
use Littlesqx\AintQueue\WorkerDirector;
use Predis\Client;

class Queue extends AbstractQueue
{
    /**
     * @var RedisPool
     */
    protected $connectionPool;

    /**
     * Queue constructor.
     *
     * @param string $channel
     * @param array  $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $channel, array $options = [])
    {
        parent::__construct($channel, $options);

        $this->connectionPool = PoolFactory::make(RedisPool::class, $options);
    }

    /**
     * Reset redis connection pool.
     *
     * @throws InvalidArgumentException
     */
    public function resetConnection(): void
    {
        $this->connectionPool = PoolFactory::make(RedisPool::class, $this->options);
    }

    /**
     * Disconnect the connection.
     */
    public function destroyConnection(): void
    {
        $this->connectionPool->flush();
    }

    /**
     * Get a connection.
     *
     * @return Client
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function getConnection()
    {
        $connection = $this->connectionPool->get();

        if (!$connection instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        return $connection;
    }

    /**
     * Release a connection.
     *
     * @param $connection
     */
    public function releaseConnection(Client $connection): void
    {
        $this->connectionPool->release($connection);
    }

    /**
     * Push an executable job message into queue.
     *
     * @param $message
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function push($message): void
    {
        $serializedMessage = null;
        $serializerType = null;

        if (\is_callable($message)) {
            $serializedMessage = $this->closureSerializer->serialize($message);
            $serializerType = Factory::SERIALIZER_TYPE_CLOSURE;
        } elseif ($message instanceof JobInterface) {
            $serializedMessage = $this->phpSerializer->serialize($message);
            $serializerType = Factory::SERIALIZER_TYPE_PHP;
        } else {
            $type = \is_object($message) ? \get_class($message) : \gettype($message);
            throw new InvalidArgumentException($type.' type message is not allowed.');
        }

        $pushMessage = \json_encode([
            'serializerType' => $serializerType,
            'serializedMessage' => $serializedMessage,
        ]);

        $redis = $this->getConnection();

        $id = $redis->incr("{$this->channelPrefix}{$this->getChannel()}:message_id");
        $redis->hset("{$this->channelPrefix}{$this->getChannel()}:messages", $id, $pushMessage);

        if ($this->pushDelay > 0) {
            $redis->zadd("{$this->channelPrefix}{$this->getChannel()}:delayed", [$id => time() + $this->pushDelay]);
        } else {
            $redis->lpush("{$this->channelPrefix}{$this->getChannel()}:waiting", [$id]);
        }

        $this->releaseConnection($redis);
    }

    /**
     * Pop a job message from waiting-queue.
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function pop()
    {
        $redis = $this->getConnection();

        $id = $redis->eval(
            LuaScripts::pop(),
            3,
            "{$this->channelPrefix}{$this->getChannel()}:waiting",
            "{$this->channelPrefix}{$this->getChannel()}:reserved",
            "{$this->channelPrefix}{$this->getChannel()}:attempts",
            1
        );

        $this->releaseConnection($redis);

        return $this->get($id);
    }

    /**
     * Remove specific finished job from current queue after exec.
     *
     * @param $id
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function remove($id)
    {
        $redis = $this->getConnection();

        $redis->hdel("{$this->channelPrefix}{$this->getChannel()}:reserved", $id);
        $redis->hdel("{$this->channelPrefix}{$this->getChannel()}:attempts", $id);
        $redis->hdel("{$this->channelPrefix}{$this->getChannel()}:messages", $id);

        $this->releaseConnection($redis);
    }

    /**
     * Release a job which was failed to execute.
     *
     * @param $id
     * @param int $delay
     *
     * @return bool
     *
     * @throws \Throwable
     * @throws RuntimeException
     */
    public function release($id, int $delay = 0)
    {
        $redis = $this->getConnection();

        $ret = $redis->eval(
            LuaScripts::release(),
            2,
            "{$this->channelPrefix}{$this->getChannel()}:delayed",
            "{$this->channelPrefix}{$this->getChannel()}:reserved",
            $id,
            \time() + $delay
        );

        $this->releaseConnection($redis);

        return $ret;
    }

    /**
     * Get status of specific job.
     *
     * @param $id
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function getStatus($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException("Invalid message ID: $id.");
        }

        $redis = $this->getConnection();

        $status = self::STATUS_DONE;

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:messages", $id)) {
            $status = self::STATUS_WAITING;
        }

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:reserved", $id)) {
            $status = self::STATUS_RESERVED;
        }

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:failed", $id)) {
            $status = self::STATUS_FAILED;
        }

        $this->releaseConnection($redis);

        return $status;
    }

    /**
     * Clear current queue.
     *
     * @return mixed
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function clear()
    {
        $redis = $this->getConnection();

        $keys = $redis->keys("{$this->channelPrefix}{$this->getChannel()}:*");
        if (!empty($keys)) {
            $redis->del($keys);
        }

        $this->releaseConnection($redis);
    }

    /**
     * Get job message from queue.
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function get($id)
    {
        if (!$id) {
            return [$id, 0, null];
        }

        $redis = $this->getConnection();

        $attempts = $redis->hget("{$this->channelPrefix}{$this->getChannel()}:attempts", $id);

        $payload = $redis->hget("{$this->channelPrefix}{$this->getChannel()}:messages", $id);

        $this->releaseConnection($redis);

        if (null === $payload) {
            return [$id, 0, null];
        }

        $message = \json_decode($payload, true);

        $serializer = Factory::getInstance($message['serializerType']);

        return [$id, $attempts, $serializer->unSerialize($message['serializedMessage'])];
    }

    /**
     * Migrate the expired job to waiting queue.
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function migrateExpired(): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::migrateExpiredJobs(),
            2,
            "{$this->channelPrefix}{$this->getChannel()}:delayed",
            "{$this->channelPrefix}{$this->getChannel()}:waiting",
            \time()
        );

        $this->releaseConnection($redis);
    }

    /**
     * Get status of current queue.
     *
     * @return array
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function status(): array
    {
        $redis = $this->getConnection();

        $total = $redis->get("{$this->channelPrefix}{$this->getChannel()}:message_id") ?? 0;

        $reserved = $redis->hlen("{$this->channelPrefix}{$this->getChannel()}:reserved");

        $waiting = $redis->llen("{$this->channelPrefix}{$this->getChannel()}:waiting");

        [$waitingProcess, $waitingProcessPool, $waitingCo] = [
            $redis->llen("{$this->channelPrefix}{$this->getChannel()}:ready:".WorkerDirector::WORKER_PROCESS),
            $redis->llen("{$this->channelPrefix}{$this->getChannel()}:ready:".WorkerDirector::WORKER_PROCESS_POOL),
            $redis->llen("{$this->channelPrefix}{$this->getChannel()}:ready:".WorkerDirector::WORKER_CO),
        ];

        $delayed = $redis->zcount("{$this->channelPrefix}{$this->getChannel()}:delayed", '-inf', '+inf');

        $failed = $redis->hlen("{$this->channelPrefix}{$this->getChannel()}:failed");

        $this->releaseConnection($redis);

        $done = $total - $waiting - $waitingProcess - $waitingProcessPool - $waitingCo - $delayed - $reserved - $failed;

        return [[$waiting, $waitingProcess, $waitingProcessPool, $waitingCo], $reserved, $delayed, $done, $failed, $total];
    }

    /**
     * Ready a job onto worker queue.
     *
     * @param int    $id
     * @param string $worker
     * @param bool   $ontoFront
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function ready($id, string $worker, bool $ontoFront = false)
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::push(),
            2,
            "{$this->channelPrefix}{$this->getChannel()}:ready:{$worker}",
            "{$this->channelPrefix}{$this->getChannel()}:reserved",
            $id,
            $ontoFront ? 1 : 0
        );

        $this->releaseConnection($redis);
    }

    /**
     * @param string $worker
     *
     * @return int
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function popReady(string $worker)
    {
        $redis = $this->getConnection();

        $id = $redis->eval(
            LuaScripts::pop(),
            3,
            "{$this->channelPrefix}{$this->getChannel()}:ready:{$worker}",
            "{$this->channelPrefix}{$this->getChannel()}:reserved",
            "{$this->channelPrefix}{$this->getChannel()}:attempts"
        );

        $this->releaseConnection($redis);

        return $id;
    }

    /**
     * Fail a job.
     *
     * @param int         $id
     * @param string|null $payload
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function failed($id, $payload = null)
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::fail(),
            2,
            "{$this->channelPrefix}{$this->getChannel()}:failed",
            "{$this->channelPrefix}{$this->getChannel()}:reserved",
            (int) $id,
            (string) $payload
        );

        $this->releaseConnection($redis);
    }

    /**
     * Retry reserved job (only called when listener restart.).
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function retryReserved()
    {
        $redis = $this->getConnection();

        $ids = $redis->hgetall("{$this->channelPrefix}{$this->getChannel()}:reserved");
        foreach ($ids as $id => $attempts) {
            $this->release($id);
        }

        $this->releaseConnection($redis);
    }
}
