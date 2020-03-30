<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

use Littlesqx\AintQueue\Compressable;
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Exception\SerializationException;

/**
 * Class CompressingSerializer.
 *
 * @see https://github.com/amphp/serialization/blob/master/src/CompressingSerializer.php
 */
class CompressingSerializer extends PhpSerializer
{
    private const FLAG_COMPRESSED = 1;

    /**
     * @param object $object
     *
     * @return string
     *
     * @throws InvalidArgumentException
     * @throws SerializationException
     */
    public function serialize($object): string
    {
        if (!$object instanceof Compressable) {
            throw new InvalidArgumentException('Argument invalid, it must be an instance of Compressable.');
        }
        $serialized = parent::serialize($object);

        $flags = 0;

        if (strlen($serialized) >= $object->getCompressingThreshold()) {
            $serialized = @\gzdeflate($serialized, 1);
            if (false === $serialized) {
                $error = \error_get_last();
                throw new SerializationException(sprintf('Could not compress data: '.($error['message'] ?? 'unknown error')));
            }
            $flags |= self::FLAG_COMPRESSED;
        }

        return \chr($flags & 0xff).$serialized;
    }

    /**
     * @param string $serialized
     *
     * @return object
     *
     * @throws SerializationException
     */
    public function unSerialize(string $serialized)
    {
        $firstByte = \ord($serialized[0]);
        $serialized = \substr($serialized, 1);

        if ($firstByte & self::FLAG_COMPRESSED) {
            $serialized = @\gzinflate($serialized);
            if (false === $serialized) {
                $error = \error_get_last();
                throw new SerializationException(sprintf('Could not decompress data: '.($error['message'] ?? 'unknown error')));
            }
        }

        return parent::unSerialize($serialized);
    }
}
