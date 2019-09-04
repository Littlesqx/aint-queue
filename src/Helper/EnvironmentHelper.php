<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
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

    /**
     * Get current process memory usage.
     *
     * @return float
     */
    public static function getCurrentMemoryUsage(): float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }
}
