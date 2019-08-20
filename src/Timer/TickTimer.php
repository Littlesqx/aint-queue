<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/19.
 */

namespace Littlesqx\AintQueue\Timer;

use Swoole\Timer as SwooleTimer;

class TickTimer implements TickTimerInterface
{
    /**
     * @var int
     */
    protected $delay;

    /**
     * @var \Closure
     */
    protected $function;

    /**
     * @var int
     */
    protected $id;

    public function __construct(int $delay, \Closure $function)
    {
        $this->delay = $delay;
        $this->function = $function;
    }

    /**
     * The time, in milliseconds (thousandths of a second), the timer should delay in between executions.
     *
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * A function to be executed every delay milliseconds.
     *
     * @return \Closure
     */
    public function getFunction(): \Closure
    {
        return $this->function;
    }

    /**
     * Start current timer.
     *
     * @return int timer's id
     */
    public function start(): int
    {
        $this->id = SwooleTimer::tick($this->delay, $this->function);

        return $this->id;
    }

    /**
     * Get current tick timer's id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id ?? 0;
    }

    /**
     * Clear current tick timer.
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->getId() >= 0 && SwooleTimer::clear($this->id);
    }
}
