<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/23.
 */

namespace Littlesqx\AintQueue\Helper;

class SwooleHelper
{
    public static function setProcessName(string $name): void
    {
        PHP_OS == 'Linux' && swoole_set_process_name($name);
    }
}
