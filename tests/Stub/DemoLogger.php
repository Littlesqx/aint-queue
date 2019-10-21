<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Stub;

use Littlesqx\AintQueue\Logger\LoggerInterface;

class DemoLogger implements LoggerInterface
{
    /**
     * Reset the log connection, if exist.
     */
    public function resetConnection(): void
    {
        // TODO: Implement resetConnection() method.
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     */
    public function emergency($message, array $context = [])
    {
        // TODO: Implement emergency() method.
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     */
    public function alert($message, array $context = [])
    {
        // TODO: Implement alert() method.
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     */
    public function critical($message, array $context = [])
    {
        // TODO: Implement critical() method.
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     */
    public function error($message, array $context = [])
    {
        // TODO: Implement error() method.
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     */
    public function warning($message, array $context = [])
    {
        // TODO: Implement warning() method.
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     */
    public function notice($message, array $context = [])
    {
        // TODO: Implement notice() method.
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     */
    public function info($message, array $context = [])
    {
        // TODO: Implement info() method.
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     */
    public function debug($message, array $context = [])
    {
        // TODO: Implement debug() method.
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = [])
    {
        // TODO: Implement log() method.
    }
}
