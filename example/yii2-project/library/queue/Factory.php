<?php

namespace app\library\queue;

use Littlesqx\AintQueue\Driver\DriverFactory;

class Factory
{
    public static function make(string $channel)
    {
        $config = require __DIR__.'/../../config/aint-queue.php';

        $driverOption = $config[$channel]['driver'] ?? [];

        return DriverFactory::make($channel, $driverOption);
    }
}