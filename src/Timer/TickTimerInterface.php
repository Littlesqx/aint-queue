<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Timer;

interface TickTimerInterface
{
    /**
     * The time, in milliseconds (thousandths of a second), the timer should delay in between executions.
     *
     * @return int
     */
    public function getDelay(): int;

    /**
     * A function to be executed every delay milliseconds.
     *
     * @return \Closure
     */
    public function getFunction(): \Closure;

    /**
     * Start current timer.
     *
     * @return int timer's id
     */
    public function start(): int;

    /**
     * Get current tick timer's id.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Clear current tick timer.
     *
     * @return bool
     */
    public function clear(): bool;
}
