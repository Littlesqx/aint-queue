<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/19.
 */

namespace Littlesqx\AintQueue\Helper;

use Symfony\Component\Process\PhpExecutableFinder;

class EnvironmentHelper
{
    /**
     * @var string
     */
    protected static $phpBinary;

    /**
     * Get the path of PHP binary file.
     *
     * @return string
     */
    public static function getPhpBinary(): string
    {
        if (null === self::$phpBinary) {
            self::$phpBinary = (new PhpExecutableFinder())->find();
        }

        return self::$phpBinary;
    }

    /**
     * Get the path of current app's PHP binary file.
     *
     * @return string
     */
    public static function getAppBinary(): ?string
    {
        return $_SERVER['SCRIPT_FILENAME'] ?? null;
    }
}
