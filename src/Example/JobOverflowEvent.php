<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/09/05.
 */

namespace Littlesqx\AintQueue\Example;

use Littlesqx\AintQueue\Event\HandlerInterface;

class JobOverflowEvent implements HandlerInterface
{

    /**
     * Handle event
     *
     * @param string $message
     * @param $error
     * @param $payload
     * @return mixed
     */
    public function handle(string $message, $error, $payload)
    {
        var_dump(
            $message,
            $error,
            $payload
        );
    }
}