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
    protected $channelPrefix = 'aint-queue';

    /**
     * @var string
     */
    protected $channel = 'default';

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
     * @var array
     */
    protected $options;

    public function __construct(string $channel, array $options = [])
    {
        $this->channel = $channel;
        $this->options = $options;
        $this->phpSerializer = new PhpSerializer();
        $this->closureSerializer = new ClosureSerializer();
    }

    /**
     * Get name of the channel.
     *
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channelPrefix.':'.$this->channel;
    }

    /**
     * Moved the expired job to waiting queue.
     */
    abstract public function migrateExpired(): void;

    /**
     * Delay to execute the job.
     *
     * @param int $delay
     *
     * @return $this
     */
    public function delay(int $delay)
    {
        $this->pushDelay = $delay;

        return $this;
    }

    abstract public function getReady(string $worker);
}
