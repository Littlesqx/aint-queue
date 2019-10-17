<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use SuperClosure\Serializer as SuperClosureSerializer;

class ClosureSerializer implements SerializerInterface
{
    protected $executor;

    public function __construct()
    {
        $this->executor = new SuperClosureSerializer();
    }

    /**
     * Serialize an object to string.
     *
     * @param \Closure $closure
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function serialize($closure): string
    {
        if (!is_callable($closure)) {
            throw new InvalidArgumentException('Argument invalid, it must be callable.');
        }

        return $this->executor->serialize($closure);
    }

    /**
     * Recover serialized string to object.
     *
     * @param string $serialized
     *
     * @return \Closure
     */
    public function unSerialize(string $serialized)
    {
        return $this->executor->unserialize($serialized);
    }
}
