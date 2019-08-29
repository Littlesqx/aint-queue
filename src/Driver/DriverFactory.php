<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/29.
 */

namespace Littlesqx\AintQueue\Driver;

use Littlesqx\AintQueue\Exception\InvalidDriverException;
use Littlesqx\AintQueue\QueueInterface;

class DriverFactory
{

    /**
     * Make a instance of QueueInterface.
     *
     * @param string $driverClass
     * @param string $channel
     * @param array $options
     *
     * @return QueueInterface
     *
     * @throws InvalidDriverException
     */
    public static function make(string $driverClass, string $channel, array $options = []): QueueInterface
    {
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