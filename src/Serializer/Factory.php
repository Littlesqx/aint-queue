<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/20.
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
    public static function make(string $type):? Serializer
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