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
use Predis\Client;

class Queue extends AbstractQueue
{
    /**
     * @see Queue::isWaiting()
     */
    const STATUS_WAITING = 1;
    /**
     * @see Queue::isReserved()
     */
    const STATUS_RESERVED = 2;
    /**
     * @see Queue::isDone()
     */
    const STATUS_DONE = 3;

    const STATUS_FAILED = 4;

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

        if (is_callable($message)) {
            $serializedMessage = $this->closureSerializer->serialize($message);
            $serializerType = Factory::SERIALIZER_TYPE_CLOSURE;
        } elseif ($message instanceof JobInterface) {
            $serializedMessage = $this->phpSerializer->serialize($message);
            $serializerType = Factory::SERIALIZER_TYPE_PHP;
        } else {
            $type = is_object($message) ? get_class($message) : gettype($message);
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

        $id = $redis->brpop(["{$this->channelPrefix}{$this->getChannel()}:waiting"], 0)[1] ?? 0;

        if ($id) {
            // reserved: {id} => attempts
            $redis->eval(
                LuaScripts::reserve(),
                2,
                "{$this->channelPrefix}{$this->getChannel()}:reserved",
                "{$this->channelPrefix}{$this->getChannel()}:attempts",
                $id
            );
        }

        $this->releaseConnection($redis);

        return $this->get($id);
    }

    /**
     * Remove specific finished job from current queue.
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
            time() + $delay
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

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:reserved", $id)) {
            $status = self::STATUS_RESERVED;
        }

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:failed", $id)) {
            $status = self::STATUS_FAILED;
        }

        if ($redis->hexists("{$this->channelPrefix}{$this->getChannel()}:messages", $id)) {
            $status = self::STATUS_WAITING;
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
     * Get current queue's size.
     *
     * @return int
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function size(): int
    {
        $redis = $this->getConnection();

        $size = $redis->eval(
            LuaScripts::size(),
            3,
            "{$this->channelPrefix}{$this->getChannel()}:waiting",
            "{$this->channelPrefix}{$this->getChannel()}:delayed",
            "{$this->channelPrefix}{$this->getChannel()}:reserved"
        );

        $this->releaseConnection($redis);

        return $size;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function isWaiting(int $id): bool
    {
        return self::STATUS_WAITING === $this->getStatus($id);
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function isReserved(int $id): bool
    {
        return self::STATUS_RESERVED === $this->getStatus($id);
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function isDone(int $id): bool
    {
        return self::STATUS_DONE === $this->getStatus($id);
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
            time()
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

        $waiting = $redis->llen("{$this->channelPrefix}{$this->getChannel()}:waiting");
        $delayed = $redis->zcount("{$this->channelPrefix}{$this->getChannel()}:delayed", '-inf', '+inf');
        $reserved = $redis->hlen("{$this->channelPrefix}{$this->getChannel()}:reserved");
        $failed = $redis->hlen("{$this->channelPrefix}{$this->getChannel()}:failed");
        $total = $redis->get("{$this->channelPrefix}{$this->getChannel()}:message_id") ?? 0;

        $this->releaseConnection($redis);

        $done = $total - $waiting - $delayed - $reserved - $failed;

        return [$waiting, $delayed, $reserved, $done, $failed, $total];
    }

    /**
     * Ready a job onto worker queue.
     *
     * @param $id
     * @param string $worker
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function ready($id, string $worker)
    {
        $redis = $this->getConnection();

        $redis->lpush("{$this->channelPrefix}{$this->getChannel()}:ready:{$worker}", [$id]);

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

        $messageId = $redis->brpop(["{$this->channelPrefix}{$this->getChannel()}:ready:{$worker}"], 0)[1] ?? 0;

        $this->releaseConnection($redis);

        return $messageId;
    }

    /**
     * Fail a job.
     *
     * @param $id
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function failed($id)
    {
        $redis = $this->getConnection();

        $redis->hdel("{$this->channelPrefix}{$this->getChannel()}:reserved", $id);
        $redis->hset("{$this->channelPrefix}{$this->getChannel()}:failed", $id, time());

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
