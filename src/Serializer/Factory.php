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

class Factory
{
    /**
     * @const string
     */
    const SERIALIZER_TYPE_PHP = 'php_serializer';

    /**
     * @const string
     */
    const SERIALIZER_TYPE_CLOSURE = 'closure_serializer';

    /**
     * @var SerializerInterface[]
     */
    public static $instances = [];

    /**
     * Get a instance for serializer.
     *
     * @param string $type
     *
     * @return SerializerInterface
     *
     * @throws InvalidArgumentException
     */
    public static function getInstance(string $type): SerializerInterface
    {
        if (!in_array($type, [self::SERIALIZER_TYPE_PHP, self::SERIALIZER_TYPE_CLOSURE], true)) {
            throw new InvalidArgumentException("The arg type: {$type} is invalid.");
        }
        if (!isset(self::$instances[$type])) {
            self::$instances[$type] = self::make($type);
        }

        return self::$instances[$type];
    }

    /**
     * Make an serializer object.
     *
     * @param string $type
     *
     * @return SerializerInterface|null
     */
    public static function make(string $type): ? SerializerInterface
    {
        switch ($type) {
            case self::SERIALIZER_TYPE_PHP:
                return new PhpSerializer();
            case self::SERIALIZER_TYPE_CLOSURE:
                return new ClosureSerializer();
            default:
                return null;
        }
    }
}
