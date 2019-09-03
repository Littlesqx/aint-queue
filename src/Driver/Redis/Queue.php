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
use Littlesqx\AintQueue\Connection\PoolInterface;
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

    /**
     * @var RedisPool
     */
    protected $redisPool;

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
        parent::__construct($channel);

        $this->redisPool = PoolFactory::make(RedisPool::class, $options);
    }

    public function setRedisPool(PoolInterface $pool)
    {
        $this->redisPool = $pool;

        return $this;
    }

    public function getRedisPool()
    {
        return $this->redisPool;
    }

    /**
     * Push an executable job message into queue.
     *
     * @param $message
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
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

        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $id = $redis->incr("{$this->getChannel()}:message_id");
        $redis->hset("{$this->getChannel()}:messages", $id, $pushMessage);

        if ($this->pushDelay > 0) {
            $redis->zadd("{$this->getChannel()}:delayed", [$id => time() + $this->pushDelay]);
        } else {
            $redis->lpush("{$this->getChannel()}:waiting", [$id]);
        }

        $this->redisPool->release($redis);
    }

    /**
     * Pop an job message from queue.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function pop()
    {
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $id = $redis->brpop(["{$this->getChannel()}:waiting"], 0)[1] ?? 0;

        // reserved: {id} => attempts
        $redis->eval(
            LuaScripts::reserve(),
            2,
            "{$this->getChannel()}:reserved",
            "{$this->getChannel()}:attempts",
            $id
        );

        $this->redisPool->release($redis);

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
     * @throws RuntimeException
     */
    public function remove($id)
    {
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $redis->hdel("{$this->getChannel()}:reserved", $id);
        $redis->hdel("{$this->getChannel()}:attempts", $id);
        $redis->hdel("{$this->getChannel()}:messages", $id);

        $this->redisPool->release($redis);
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
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }
        $ret = $redis->eval(
            LuaScripts::release(),
            2,
            "{$this->getChannel()}:delayed",
            "{$this->getChannel()}:reserved",
            $id,
            time() + $delay
        );

        $this->redisPool->release($redis);

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

        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $status = self::STATUS_DONE;

        if ($redis->hexists("{$this->getChannel()}:reserved", $id)) {
            $status = self::STATUS_RESERVED;
        }

        if ($redis->hexists("{$this->getChannel()}:messages", $id)) {
            $status = self::STATUS_WAITING;
        }

        $this->redisPool->release($redis);

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
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $keys = $redis->keys("{$this->getChannel()}:*");
        $redis->del($keys);

        $this->redisPool->release($redis);
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
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $attempts = $redis->hget("{$this->getChannel()}:attempts", $id);

        $payload = $redis->hget("{$this->getChannel()}:messages", $id);

        $this->redisPool->release($redis);

        if (null === $payload) {
            return [$id, 0, null];
        }

        $message = \json_decode($payload, true);

        $serializer = Factory::getInstance($message['serializerType']);

        return [$id, $attempts, $serializer->unSerialize($message['serializedMessage'])];
    }

    /**
     * Get channel name of current queue.
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
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
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $size = $redis->eval(
            LuaScripts::size(),
            3,
            "{$this->getChannel()}:waiting",
            "{$this->getChannel()}:delayed",
            "{$this->getChannel()}:reserved"
        );

        $this->redisPool->release($redis);

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
        echo "Hello ~\n";
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $redis->eval(
            LuaScripts::migrateExpiredJobs(),
            2,
            "{$this->getChannel()}:delayed",
            "{$this->getChannel()}:waiting",
            time()
        );

        $this->redisPool->release($redis);
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
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $waiting = $redis->llen("{$this->getChannel()}:waiting");
        $delayed = $redis->zcount("{$this->getChannel()}:delayed", '-inf', '+inf');
        $reserved = $redis->hlen("{$this->getChannel()}:reserved");
        $total = $redis->get("{$this->getChannel()}:message_id") ?? 0;

        $this->redisPool->release($redis);

        $done = $total - $waiting - $delayed - $reserved;

        return [$waiting, $delayed, $reserved, $done, $total];
    }

    /**
     * @param string $worker
     * @param $messageId
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function ready(string $worker, $messageId)
    {
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $redis->lpush("{$this->getChannel()}:ready:{$worker}", [$messageId]);

        $this->redisPool->release($redis);
    }

    /**
     * @param string $worker
     *
     * @return int
     *
     * @throws RuntimeException
     * @throws \Throwable
     */
    public function getReady(string $worker)
    {
        /** @var Client $redis */
        $redis = $this->redisPool->get();

        if (!$redis instanceof Client) {
            throw new RuntimeException('[Error] can not pop a redis connection from pool.');
        }

        $messageId = $redis->brpop(["{$this->getChannel()}:ready:{$worker}"], 0)[1] ?? 0;

        $this->redisPool->release($redis);

        return $messageId;
    }
}
