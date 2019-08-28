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
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
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

    protected $redis;

    public function __construct(string $topic)
    {
        parent::__construct($topic);

        $this->redis = new Client(['read_write_timeout' => 0]);
    }

    /**
     * Push an executable job message into queue.
     *
     * @param $message
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
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

        $id = $this->redis->incr("{$this->channel}.{$this->topic}.message_id");
        $this->redis->hset("{$this->channel}.{$this->topic}.messages", $id, $pushMessage);

        if ($this->pushDelay > 0) {
            $this->redis->zadd("{$this->channel}.{$this->topic}.delayed", [$id => time() + $this->pushDelay]);
        } else {
            $this->redis->lpush("{$this->channel}.{$this->topic}.waiting", [$id]);
        }
    }

    /**
     * Pop an job message from queue.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function pop()
    {
        $id = $this->redis->brpop(["{$this->channel}.{$this->topic}.waiting"], 0)[1] ?? 0;

        // reserved: {id} => attempts
        $this->redis->eval(
            LuaScripts::reserve(),
            2,
            "{$this->channel}.{$this->topic}.reserved",
            "{$this->channel}.{$this->topic}.attempts",
            $id
        );

        return $this->get($id);
    }

    /**
     * Remove specific finished job from current queue.
     *
     * @param $id
     *
     * @return mixed
     */
    public function remove($id)
    {
        $this->redis->hdel("$this->channel.{$this->topic}.reserved", $id);
        $this->redis->hdel("$this->channel.{$this->topic}.attempts", $id);
        $this->redis->hdel("$this->channel.{$this->topic}.messages", $id);
    }

    /**
     * Release a job which was failed to execute.
     *
     * @param $id
     * @param int $delay
     *
     * @return bool
     */
    public function release($id, int $delay = 0)
    {
        return $this->redis->eval(
            LuaScripts::release(),
            2,
            "{$this->channel}.{$this->topic}.delayed",
            "{$this->channel}.{$this->topic}.reserved",
            $id,
            time() + $delay
        );
    }

    /**
     * Get status of specific job.
     *
     * @param $id
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException("Invalid message ID: $id.");
        }

        if ($this->redis->hexists("$this->channel.reserved", $id)) {
            return self::STATUS_RESERVED;
        }

        if ($this->redis->hexists("$this->channel.messages", $id)) {
            return self::STATUS_WAITING;
        }

        return self::STATUS_DONE;
    }

    /**
     * Clear current queue.
     *
     * @return mixed
     */
    public function clear()
    {
        $keys = $this->redis->keys("{$this->channel}.{$this->topic}.*");
        $this->redis->del($keys);
    }

    /**
     * Get job message from queue.
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function get($id)
    {
        $attempts = $this->redis->hget("{$this->channel}.{$this->topic}.attempts", $id);

        $payload = $this->redis->hget("{$this->channel}.{$this->topic}.messages", $id);

        if (null === $payload) {
            return [$id, 0, null];
        }

        $message = \json_decode($payload, true);

        $serializer = Factory::getInstance($message['serializerType']);

        return [$id, $attempts, $serializer->unSerialize($message['serializedMessage'])];
    }

    /**
     * Get topic name of current queue.
     *
     * @return string
     */
    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * Get current queue's size.
     *
     * @return int
     */
    public function size(): int
    {
        return $this->redis->eval(
            LuaScripts::size(),
            3,
            "{$this->channel}.{$this->topic}.waiting",
            "{$this->channel}.{$this->topic}.delayed",
            "{$this->channel}.{$this->topic}.reserved"
        );
    }

    /**
     *
     * @param int $id of a job message
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isWaiting(int $id): bool
    {
        return $this->status($id) === self::STATUS_WAITING;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isReserved(int $id): bool
    {
        return $this->status($id) === self::STATUS_RESERVED;
    }

    /**
     * @param int $id of a job message
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isDone(int $id): bool
    {
        return $this->status($id) === self::STATUS_DONE;
    }

    /**
     * Migrate the expired job to waiting queue.
     */
    public function migrateExpired(): void
    {
        $this->redis->eval(
            LuaScripts::migrateExpiredJobs(),
            2,
            "{$this->channel}.{$this->topic}.delayed",
            "{$this->channel}.{$this->topic}.waiting",
            time()
        );
    }

}
