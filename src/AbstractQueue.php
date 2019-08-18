<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

abstract class AbstractQueue implements QueueInterface
{
    protected $channel = '';

    protected $topic = 'default';

    /** @var AbstractQueue[] */
    protected static $instances = [];

    public function __construct(string $topic)
    {
        $this->topic = $topic;
    }

    /**
     * Get a singleton for queue.
     *
     * @param $topic
     *
     * @return AbstractQueue
     */
    public static function getInstance($topic): AbstractQueue
    {
        if (!isset(self::$instances[$topic])) {
            self::$instances[$topic] = new static($topic);
        }

        return self::$instances[$topic];
    }
}
