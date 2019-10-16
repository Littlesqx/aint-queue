<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue\Driver;

use Littlesqx\AintQueue\Exception\InvalidDriverException;
use Littlesqx\AintQueue\QueueInterface;

class DriverFactory
{
    /**
     * Make a instance of QueueInterface.
     *
     * @param string $channel
     * @param array  $options
     *
     * @return QueueInterface
     *
     * @throws InvalidDriverException
     */
    public static function make(string $channel, array $options = []): QueueInterface
    {
        $driverClass = $options['class'] ?? '';
        if (!class_exists($driverClass)) {
            throw new InvalidDriverException(sprintf('[Error] class %s is not found.', $driverClass));
        }

        $driver = new $driverClass($channel, $options);

        if (!$driver instanceof QueueInterface) {
            throw new InvalidDriverException(sprintf('[Error] class %s is not instanceof %s.', $driverClass, QueueInterface::class));
        }

        return $driver;
    }
}
