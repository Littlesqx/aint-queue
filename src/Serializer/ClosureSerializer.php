<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

use SuperClosure\Serializer as SuperClosureSerializer;

class ClosureSerializer implements Serializer
{
    protected $executor;

    public function __construct()
    {
        $this->executor = new SuperClosureSerializer();
    }

    /**
     * Serialize an object to string.
     *
     * @param $closure
     *
     * @return string
     */
    public function serializer($closure): string
    {
        if (!is_callable($closure)) {
            throw new \InvalidArgumentException('Argument invalid, it must be callable.');
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
    public function unSerializer(string $serialized)
    {
        return $this->executor->unserialize($serialized);
    }
}
