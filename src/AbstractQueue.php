<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Serializer\ClosureSerializer;
use Littlesqx\AintQueue\Serializer\PhpSerializer;

abstract class AbstractQueue implements QueueInterface
{
    /**
     * @var string
     */
    protected $channel = 'aint-queue';

    /**
     * @var string
     */
    protected $topic = 'default';

    /**
     * @var PhpSerializer
     */
    protected $phpSerializer;

    /**
     * @var ClosureSerializer
     */
    protected $closureSerializer;

    /**
     * @var int
     */
    protected $pushDelay = 0;

    /**
     * @var AbstractQueue[]
     */
    protected static $instances = [];

    public function __construct(string $topic)
    {
        $this->topic = $topic;
        $this->phpSerializer = new PhpSerializer();
        $this->closureSerializer = new ClosureSerializer();
    }

    /**
     * Get a singleton for queue.
     *
     * @param string $topic
     *
     * @return QueueInterface
     */
    public static function getInstance(string $topic): QueueInterface
    {
        if (!isset(self::$instances[$topic])) {
            self::$instances[$topic] = new static($topic);
        }

        return self::$instances[$topic];
    }

    /**
     * Get name of the channel.
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Moved the expired job to waiting queue.
     */
    public function moveExpired(): void
    {
        // TODO
    }

    /**
     * Check jobs' execution, you can register some status reporter.
     */
    public function checkStatus(): void
    {
        // TODO
    }

    public function delay(int $delay)
    {
        $this->pushDelay = $delay;

        return $this;
    }
}
