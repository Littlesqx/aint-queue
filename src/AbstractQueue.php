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
    protected $singleChannel = 'aint-queue:single';

    /**
     * @var string
     */
    protected $multipleChannel = 'aint-queue:multiple';

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
     * Get single channel by channel type.
     *
     * @return string
     */
    public function getSingleChannel(): string
    {
        return $this->singleChannel;
    }

    /**
     * Get multiple channel by channel type.
     *
     * @return string
     */
    public function getMultipleChannel(): string
    {
        return $this->multipleChannel;
    }

    public function moveExpired(): void
    {

    }

    public function checkStatus(): void
    {

    }

    public function delay(int $delay)
    {
        $this->pushDelay = $delay;

        return $this;
    }

}
