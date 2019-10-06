<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Helper;

class SwooleHelper
{
    public static function setProcessName(string $name): void
    {
        PHP_OS == 'Linux' && \swoole_set_process_name($name);
    }
}
