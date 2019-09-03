<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/03.
 */

namespace Littlesqx\AintQueue\Connection;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel as CoroutineChannel;

class Channel
{
    /**
     * @var int
     */
    protected $size;

    /**
     * @var CoroutineChannel
     */
    protected $channel;

    /**
     * @var \SplQueue
     */
    protected $queue;

    public function __construct(int $size)
    {
        $this->size = $size;
        $this->channel = new CoroutineChannel($size);
        $this->queue = new \SplQueue();
    }

    public function pop(float $timeout)
    {
        if ($this->isCoroutine()) {
            return $this->channel->pop($timeout);
        }
        return !$this->queue->isEmpty() ? $this->queue->shift() : false;
    }

    public function push($data)
    {
        if ($this->isCoroutine()) {
            return $this->channel->push($data);
        }

        $this->queue->push($data);
    }

    public function length(): int
    {
        if ($this->isCoroutine()) {
            return $this->channel->length();
        }

        return $this->queue->count();
    }

    /**
     * Whether Current runtime is coroutine.
     *
     * @return bool
     */
    protected function isCoroutine(): bool
    {
        return Coroutine::getCid() > 0;
    }
}