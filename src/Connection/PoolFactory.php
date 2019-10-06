<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Connection;

use Littlesqx\AintQueue\Exception\InvalidArgumentException;

class PoolFactory
{
    /**
     * Make a connectionPool.
     *
     * @param string $poolClass
     * @param array  $options
     *
     * @return PoolInterface
     *
     * @throws InvalidArgumentException
     */
    public static function make(string $poolClass, array $options): PoolInterface
    {
        if (!\class_exists($poolClass)) {
            throw new InvalidArgumentException(\sprintf('[Error] class %s not exists.', $poolClass));
        }

        $pool = new $poolClass($options);

        if (!$pool instanceof PoolInterface) {
            throw new InvalidArgumentException(\sprintf('[Error] class %s is not instanceof %s', $poolClass, PoolInterface::class));
        }

        return $pool;
    }
}
