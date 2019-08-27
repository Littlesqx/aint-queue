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
            throw new InvalidArgumentException(gettype($message).' type message is not allowed.');
        }

        $pushMessage = \json_encode([
            'serializerType' => $serializerType,
            'serializedMessage' => $serializedMessage,
        ]);

        $id = $this->redis->incr("{$this->channel}.{$this->topic}.message_id");
        $this->redis->hset("{$this->channel}.{$this->topic}.messages", $id, $pushMessage);

        if ($this->pushDelay <= 0) {
            $this->redis->lpush("{$this->channel}.{$this->topic}.waiting", [$id]);
        } else {
            $this->redis->zadd("{$this->channel}.{$this->topic}.delayed", [$id => time() + $this->pushDelay]);
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

        return $this->get($id);
    }

    /**
     * Remove specific job from current queue.
     *
     * @param $id
     *
     * @return mixed
     */
    public function remove($id)
    {
        // TODO: Implement remove() method.
    }

    /**
     * Get status of specific job.
     *
     * @param $id
     *
     * @return mixed
     */
    public function status($id)
    {
        // TODO: Implement status() method.
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
        $payload = $this->redis->hget("{$this->channel}.{$this->topic}.messages", $id);

        if (null === $payload) {
            return [$id, null];
        }

        $message = \json_decode($payload, true);

        $serializer = Factory::getInstance($message['serializerType']);

        return [$id, $serializer->unSerialize($message['serializedMessage'])];
    }

    public function getTopic(): string
    {
        return $this->topic;
    }
}
