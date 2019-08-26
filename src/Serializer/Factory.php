<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Serializer;

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

    /** @var Serializer[] */
    public static $instances = [];

    /**
     * Get a instance for serializer.
     *
     * @param string $type
     *
     * @return Serializer
     */
    public static function getInstance(string $type): Serializer
    {
        if (!in_array($type, [self::SERIALIZER_TYPE_PHP, self::SERIALIZER_TYPE_CLOSURE], true)) {
            throw new \InvalidArgumentException("The arg type: {$type} is invalid.");
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
     * @return Serializer|null
     */
    public static function make(string $type): ? Serializer
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
