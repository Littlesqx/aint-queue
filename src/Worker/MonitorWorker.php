<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\JobSnapshotterInterface;
use Swoole\Timer;

class MonitorWorker extends AbstractWorker
{
    /**
     * Working for handle job in loop.
     *
     * @throws \Throwable
     */
    public function work(): void
    {
        @swoole_set_process_name(sprintf('aint-queue-monitor#%s for %s', getmypid(), $this->queue->getChannel()));
        $this->logger->info(sprintf('monitor#%s for %s is started.', getmypid(), $this->queue->getChannel()));

        $this->init();

        Timer::tick(1000 * 2, function () {
            if (!$this->working) {
                Timer::clearAll();
            }
            if (!$this->workerReloadAble) {
                $this->process->exit(1);
            }
        });

        // move expired job
        $moveExpiredInterval = (int) ($this->options['job']['move_expired_interval'] ?? 2);
        Timer::tick(1000 * $moveExpiredInterval, function () {
            $this->queue->migrateExpired();
        });

        // check queue status
        $handlers = $this->options['monitor']['job_snapshot']['handler'] ?? [];
        if (!empty($handlers)) {
            $interval = (int) ($this->options['monitor']['job_snapshot']['interval'] ?? 60 * 5);
            Timer::tick(1000 * $interval, function () {
                $this->checkQueueStatus();
            });
        }

        // check worker status, create or release workers
        $flexInterval = (int) ($this->options['consumer']['flex_interval'] ?? 5 * 60);
        Timer::tick(1000 * $flexInterval, function () {
            $this->process->write(json_encode(['type' => PipeMessage::MESSAGE_TYPE_CONSUMER_FLEX]));
        });
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
                $handler->handle($snapshot);
            }
        } catch (\Throwable $t) {
            $this->logger->error('Error when exec JobSnapshotHandler, '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
            ]);

            return;
        }
    }
}
