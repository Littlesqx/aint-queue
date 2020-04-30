<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Driver\Redis;

use Littlesqx\AintQueue\AbstractQueue;
use Littlesqx\AintQueue\Compressable;
use Littlesqx\AintQueue\Connection\RedisConnector;
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\JobInterface;
use Littlesqx\AintQueue\Serializer\Factory;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use Swoole\Coroutine;

class Queue extends AbstractQueue
{
    /**
     * @var RedisConnector|Client
     */
    private $connector;

    /**
     * Queue constructor.
     *
     * @param string $channel
     * @param array  $options
     */
    public function __construct(string $channel, array $options = [])
    {
        parent::__construct($channel, $options);
        $this->initConnection();
    }

    /**
     * Reset redis connection.
     */
    public function initConnection(): void
    {
        $this->connector = RedisConnector::create($this->options['connection'] ?? []);
    }

    /**
     * Disconnect the connection.
     */
    public function destroyConnection(): void
    {
        $this->connector->disconnect();
    }

    /**
     * Get a connection.
     *
     * @return Client|RedisConnector
     */
    public function getConnection()
    {
        if (Coroutine::getCid() > 0) {
            return Coroutine::getContext()[RedisConnector::class]
                ?? (Coroutine::getContext()[RedisConnector::class] = RedisConnector::create($this->options['connection'] ?? []));
        }

        return $this->connector;
    }

    /**
     * Push an executable job message into queue.
     *
     * @param \Closure|JobInterface $message
     * @param int                   $delay
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function push($message, int $delay = 0): void
    {
        $serializedMessage = null;
        $serializerType = null;

        if (is_callable($message)) {
            $serializedMessage = $this->closureSerializer->serialize($message);
            $serializerType = Factory::SERIALIZER_TYPE_CLOSURE;
        } elseif ($message instanceof JobInterface) {
            if ($message instanceof Compressable) {
                $serializedMessage = $this->compressingSerializer->serialize($message);
                $serializerType = Factory::SERIALIZER_TYPE_COMPRESSING;
            } else {
                $serializedMessage = $this->phpSerializer->serialize($message);
                $serializerType = Factory::SERIALIZER_TYPE_PHP;
            }
        } else {
            $type = is_object($message) ? get_class($message) : gettype($message);
            throw new InvalidArgumentException($type.' type message is not allowed.');
        }

        $pushMessage = json_encode([
            'serializerType' => $serializerType,
            'serializedMessage' => $serializedMessage,
        ]);

        $redis = $this->getConnection();

        $id = $redis->incr("{$this->channelPrefix}{$this->channel}:message_id");
        $redis->hset("{$this->channelPrefix}{$this->channel}:messages", $id, $pushMessage);

        if ($delay > 0) {
            $redis->zadd("{$this->channelPrefix}{$this->channel}:delayed", [$id => time() + $delay]);
        } else {
            $redis->lpush("{$this->channelPrefix}{$this->channel}:waiting", [$id]);
        }
    }

    /**
     * Pop a job message from waiting-queue.
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function pop(): int
    {
        $redis = $this->getConnection();

        return (int) $redis->eval(
            LuaScripts::pop(),
            3,
            "{$this->channelPrefix}{$this->channel}:waiting",
            "{$this->channelPrefix}{$this->channel}:reserved",
            "{$this->channelPrefix}{$this->channel}:attempts",
            $this->options['handle_timeout'] ?? 60 * 30
        );
    }

    /**
     * Remove specific finished job from current queue after exec.
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function remove(int $id): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::remove(),
            4,
            "{$this->channelPrefix}{$this->channel}:reserved",
            "{$this->channelPrefix}{$this->channel}:attempts",
            "{$this->channelPrefix}{$this->channel}:failed",
            "{$this->channelPrefix}{$this->channel}:messages",
            $id
        );
    }

    /**
     * Release a job which was failed to execute.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function release(int $id, int $delay = 0): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::release(),
            2,
            "{$this->channelPrefix}{$this->channel}:delayed",
            "{$this->channelPrefix}{$this->channel}:reserved",
            $id,
            time() + $delay
        );
    }

    /**
     * Get status of specific job.
     *
     * @param int $id
     *
     * @return int
     *
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function getStatus(int $id): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("Invalid message ID: $id.");
        }

        $redis = $this->getConnection();

        $status = self::STATUS_DONE;

        if ($redis->hexists("{$this->channelPrefix}{$this->channel}:messages", $id)) {
            $status = self::STATUS_WAITING;
        }

        if ($redis->zscore("{$this->channelPrefix}{$this->channel}:reserved", $id)) {
            $status = self::STATUS_RESERVED;
        }

        if ($redis->hexists("{$this->channelPrefix}{$this->channel}:failed", $id)) {
            $status = self::STATUS_FAILED;
        }

        return $status;
    }

    /**
     * Clear current queue.
     *
     * @throws \Throwable
     */
    public function clear(): void
    {
        $redis = $this->getConnection();

        // delete waiting queue
        while ($redis->llen("{$this->channelPrefix}{$this->channel}:waiting") > 0) {
            $redis->ltrim("{$this->channelPrefix}{$this->channel}:waiting", 0, -501);
        }
        // delete reserved queue
        while ($redis->zcard("{$this->channelPrefix}{$this->channel}:reserved") > 0) {
            $redis->zremrangebyrank("{$this->channelPrefix}{$this->channel}:reserved", 0, 499);
        }

        // delete delayed queue
        while ($redis->zcard("{$this->channelPrefix}{$this->channel}:delayed") > 0) {
            $redis->zremrangebyrank("{$this->channelPrefix}{$this->channel}:delayed", 0, 499);
        }

        // delete failed queue
        $cursor = 0;
        do {
            [$cursor, $data] = $redis->hscan("{$this->channelPrefix}{$this->channel}:failed", $cursor, ['COUNT' => 200]);
            if (!empty($fields = array_keys($data))) {
                $redis->hdel("{$this->channelPrefix}{$this->channel}:failed", $fields);
            }
        } while ($cursor != 0);

        // delete attempts queue
        $cursor = 0;
        do {
            [$cursor, $data] = $redis->hscan("{$this->channelPrefix}{$this->channel}:attempts", $cursor, ['COUNT' => 200]);
            if (!empty($fields = array_keys($data))) {
                $redis->hdel("{$this->channelPrefix}{$this->channel}:attempts", $fields);
            }
        } while ($cursor != 0);

        // delete messages queue
        $cursor = 0;
        do {
            [$cursor, $data] = $redis->hscan("{$this->channelPrefix}{$this->channel}:messages", $cursor, ['COUNT' => 200]);
            if (!empty($fields = array_keys($data))) {
                $redis->hdel("{$this->channelPrefix}{$this->channel}:messages", $fields);
            }
        } while ($cursor != 0);

        // delete others
        $keyIterator = new Keyspace($redis->getConnector(), "{$this->channelPrefix}{$this->channel}:*", 50);
        !empty($keys = iterator_to_array($keyIterator)) && $redis->del($keys);
    }

    /**
     * Get job message from queue.
     *
     * @param int $id
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function get(int $id): array
    {
        if (!$id) {
            throw new InvalidArgumentException('Invalid id value: '.$id);
        }

        $redis = $this->getConnection();

        $attempts = $redis->hget("{$this->channelPrefix}{$this->channel}:attempts", $id);
        $payload = $redis->hget("{$this->channelPrefix}{$this->channel}:messages", $id);

        if (empty($payload) || empty($message = json_decode($payload, true)) || !isset($message['serializerType'])) {
            throw new InvalidJobException(sprintf('Broken message payload[%d]: %s', $id, $payload));
        }

        $serializer = Factory::getInstance($message['serializerType']);

        return [$id, (int) $attempts, $serializer->unSerialize($message['serializedMessage'])];
    }

    /**
     * Migrate the expired job to waiting queue.
     *
     * @throws \Throwable
     */
    public function migrateExpired(): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::migrateExpiredJobs(),
            2,
            "{$this->channelPrefix}{$this->channel}:delayed",
            "{$this->channelPrefix}{$this->channel}:waiting",
            time()
        );
    }

    /**
     * Get status of current queue.
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function status(): array
    {
        $redis = $this->getConnection();

        $pipe = $redis->pipeline();
        $pipe->get("{$this->channelPrefix}{$this->channel}:message_id");
        $pipe->zcard("{$this->channelPrefix}{$this->channel}:reserved");
        $pipe->llen("{$this->channelPrefix}{$this->channel}:waiting");
        $pipe->zcount("{$this->channelPrefix}{$this->channel}:delayed", '-inf', '+inf');
        $pipe->hlen("{$this->channelPrefix}{$this->channel}:failed");
        [$total, $reserved, $waiting, $delayed, $failed] = $pipe->execute();

        $done = ($total ?? 0) - $waiting - $delayed - $reserved - $failed;

        return [$waiting, $reserved, $delayed, $done, $failed, $total ?? 0];
    }

    /**
     * Fail a job.
     *
     * @param int         $id
     * @param string|null $payload
     *
     * @throws \Throwable
     */
    public function failed(int $id, string $payload = null): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::fail(),
            2,
            "{$this->channelPrefix}{$this->channel}:failed",
            "{$this->channelPrefix}{$this->channel}:reserved",
            (int) $id,
            (string) $payload
        );
    }

    /**
     * Retry reserved job (only called when listener restart.).
     *
     * @throws \Throwable
     */
    public function retryReserved(): void
    {
        $redis = $this->getConnection();

        $ids = $redis->zrange("{$this->channelPrefix}{$this->channel}:reserved", 0, -1);
        foreach ($ids as $id) {
            $this->release((int) $id);
        }
    }

    /**
     * Get all failed jobs.
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function getFailed(): array
    {
        $redis = $this->getConnection();

        $failedJobs = [];
        $cursor = 0;
        do {
            [$cursor, $data] = $redis->hscan("{$this->channelPrefix}{$this->channel}:failed", $cursor, [
                'COUNT' => 10,
            ]);
            $failedJobs += $data;
        } while ($cursor != 0);

        return $failedJobs;
    }

    /**
     * Clear failed job.
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public function clearFailed(int $id): void
    {
        $redis = $this->getConnection();

        $redis->hdel("{$this->channelPrefix}{$this->channel}:failed", [$id]);
    }

    /**
     * Reload failed job.
     *
     * @param int $id
     * @param int $delay
     *
     * @throws \Throwable
     */
    public function reloadFailed(int $id, int $delay = 0): void
    {
        $redis = $this->getConnection();

        $redis->eval(
            LuaScripts::reloadFail(),
            2,
            "{$this->channelPrefix}{$this->channel}:delayed",
            "{$this->channelPrefix}{$this->channel}:failed",
            $id,
            time() + $delay
        );
    }
}
