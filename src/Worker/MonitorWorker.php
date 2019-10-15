<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/10/15.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\JobSnapshotHandlerInterface;
use Swoole\Timer;

class MonitorWorker extends AbstractWorker
{
    /**
     * Working for handle job in loop.
     */
    public function work(): void
    {
        @swoole_set_process_name(sprintf('aint-queue-monitor#%s for %s', getmypid(), $this->queue->getChannel()));
        $this->logger->info(sprintf('monitor#%s for %s is started.', getmypid(), $this->queue->getChannel()));

        $this->init();

        // move expired job
        Timer::tick(1000, function () {
            $this->queue->migrateExpired();
        });

        // check queue status
        $handlers = $this->options['monitor']['job_snapshot']['handler'] ?? [];
        if (!empty($handlers)) {
            $interval = (int) $this->options['monitor']['job_snapshot']['interval'] ?? 60 * 5;
            Timer::tick(1000 * $interval, function () {
                $this->checkQueueStatus();
            });
        }

        if ($this->options['consumer']['dynamic_mode'] ?? false) {
            // check worker status, create or release workers
            Timer::tick(1000 * 5, function () {
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
                if (!$handler instanceof JobSnapshotHandlerInterface) {
                    $this->logger->warning('JobSnapshotHandler must implement JobSnapshotHandlerInterface.');
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