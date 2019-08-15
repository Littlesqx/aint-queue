<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

interface Serializer
{
    /**
     * Serialize an object to string.
     *
     * @param $object
     *
     * @return string
     */
    public function serializer($object): string;

    /**
     * Recover serialized string to object.
     *
     * @param string $serialized
     *
     * @return object
     */
    public function unSerializer(string $serialized);
}
