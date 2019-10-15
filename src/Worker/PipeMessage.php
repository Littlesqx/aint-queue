<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/10/15.
 */

namespace Littlesqx\AintQueue\Worker;

class PipeMessage
{
    /**
     * @var string
     */
    protected $origin;

    /**
     * @var array
     */
    protected $content;

    /**
     * @const int
     */
    const MESSAGE_TYPE_CONSUMER_FLEX = 1;

    public function __construct(string $origin)
    {
        $this->origin = $origin;
        $this->content = json_decode($origin, true) ?? [];
    }

    public function type(): int
    {
        return (int) ($this->content['type'] ?? 0);
    }

    public function payload(): array
    {
        return $this->content['payload'] ?? [];
    }
}
