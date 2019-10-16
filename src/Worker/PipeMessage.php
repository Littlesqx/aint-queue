<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

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
