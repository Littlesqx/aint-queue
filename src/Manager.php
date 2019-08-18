<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Logger\DefaultLogger;
use Psr\Log\LoggerInterface;
use Swoole\Process;

class Manager
{
    /** @var LoggerInterface */
    protected $logger;

    protected function init()
    {
        $this->registerSignal();
    }

    protected function registerSignal()
    {
        // force exit
        Process::signal(SIGTERM, function ($signo) {
        });
        // force killed
        Process::signal(SIGKILL, function ($signo) {
        });
        // custom signal - exit smoothly
        Process::signal(SIGUSR1, function ($signo) {
        });
        // custom signal - record process status
        Process::signal(SIGUSR2, function ($signo) {
        });
    }

    protected function registerTimer()
    {
        // check queue status

        // move expired job
    }

    /**
     * Set a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get current work logger.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        $this->logger = $this->logger ?? new DefaultLogger();

        return $this->logger;
    }
}
