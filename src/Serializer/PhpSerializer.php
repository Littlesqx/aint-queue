<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

use Littlesqx\AintQueue\Exception\InvalidArgumentException;

class PhpSerializer implements SerializerInterface
{
    /**
     * Serialize an object to string.
     *
     * @param $object
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function serialize($object): string
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException('Argument invalid, it must be an object.');
        }

        return serialize($object);
    }

    /**
     * Recover serialized string to object.
     *
     * @param string $serialized
     *
     * @return object
     */
    public function unSerialize(string $serialized)
    {
        return unserialize($serialized);
    }
}
