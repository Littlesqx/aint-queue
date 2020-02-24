<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\JobSnapshotterInterface;
use Swoole\Coroutine;
use Swoole\Timer;

class MonitorWorker extends AbstractWorker
{
    /**
     * @var int[]
     */
    protected $timers = [];

    /**
     * Working for handle job in loop.
     *
     * @throws \Throwable
     */
    public function work(): void
    {
        @swoole_set_process_name(sprintf('aint-queue: monitor#%s', $this->queue->getChannel()));
        $this->logger->debug(sprintf('monitor#%s for %s is started.', getmypid(), $this->queue->getChannel()));

        $this->init();

        $this->timers[] = Timer::tick(1000 * 2, function () {
            if (!$this->working) {
                $this->clearTimers();
            }
            if (!$this->workerReloadAble) {
                $this->process->exit(1);
            }
        });

        // move expired job
        $this->timers[] = Timer::tick(1000, function () {
            $this->queue->migrateExpired();
        });

        // check queue status
        $handlers = $this->options['job_snapshot']['handler'] ?? [];
        if (!empty($handlers)) {
            $interval = (int) ($this->options['job_snapshot']['interval'] ?? 60 * 5);
            $this->timers[] = Timer::tick(1000 * $interval, function () {
                $this->checkQueueStatus();
            });
        }

        // check worker status, create or release workers
        $isDynamic = $this->options['consumer']['dynamic_mode'] ?? false;
        if ($isDynamic) {
            $flexInterval = (int) ($this->options['consumer']['flex_interval'] ?? 5 * 60);
            $this->timers[] = Timer::tick(1000 * $flexInterval, function () {
                $this->process->write(json_encode(['type' => PipeMessage::MESSAGE_TYPE_CONSUMER_FLEX]));
            });
        }
    }

    /**
     * Check current queue's running status.
     */
    protected function checkQueueStatus()
    {
        try {
            [$waiting, $reserved, $delayed, $done, $failed, $total] = $this->queue->status();
            $snapshot = compact('waiting', 'reserved', 'delayed', 'done', 'failed', 'total');
            $handlers = $this->options['job_snapshot']['handler'] ?? [];
            foreach ($handlers as $handler) {
                if (!is_string($handler) || !class_exists($handler)) {
                    $this->logger->warning('Invalid JobSnapshotHandler or class not exists.');
                    continue;
                }
                $handler = new $handler();
                if (!$handler instanceof JobSnapshotterInterface) {
                    $this->logger->warning('JobSnapshotHandler must implement JobSnapshotterInterface.');
                    continue;
                }
                Coroutine::create(function () use ($handler, $snapshot) {
                    $handler->handle($snapshot);
                });
            }
        } catch (\Throwable $t) {
            $this->logger->error('Error when exec JobSnapshotHandler, '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
            ]);
        }
    }

    /**
     * Clear all timers.
     */
    protected function clearTimers(): void
    {
        foreach ($this->timers as $timer) {
            Timer::clear($timer);
        }
    }
}
