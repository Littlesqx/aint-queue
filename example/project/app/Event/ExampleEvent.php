<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/06.
 */

namespace App\Event;

use Littlesqx\AintQueue\Event\HandlerInterface;

class ExampleEvent implements HandlerInterface
{

    /**
     * Handle event.
     *
     * @param string $message
     * @param $error
     * @param $payload
     *
     * @return mixed
     */
    public function handle(string $message, $error, $payload)
    {
        echo "$message \n";
    }
}