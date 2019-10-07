<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Worker;

use Littlesqx\AintQueue\Exception\CoroutineNumberExceedException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Littlesqx\AintQueue\WorkerDirector;
use Swoole\Coroutine;

class CoroutineWorker extends AbstractWorker
{
    /**
     * @var string
     */
    protected $name = WorkerDirector::WORKER_CO;

    /**
     * Run tasks in loop.
     */
    public function work(): void
    {
        $this->initWorker();

        Coroutine::set([
            'max_coroutine' => $this->options['max_coroutine'] ?? 4096,
        ]);

        Coroutine::create(function () {
            Coroutine::defer(function () {
                $this->exitWorker();
            });
            while ($this->working) {
                $messageId = $this->queue->popReady($this->name);
                if (!$messageId) {
                    Coroutine::sleep(1);
                    continue;
                }
                // If current worker is stopped,
                // the job popped will be push onto ready queue again.
                if (!$this->working) {
                    $this->queue->ready($messageId, $this->name, true);
                    break;
                }
                try {
                    Coroutine::create([$this, 'executeJob'], $messageId);
                } catch (\Throwable $t) {
                    $e = \get_class($t);
                    $this->logger->error("Job exec error,  {$e}: {$t->getMessage()}", [
                        'driver' => \get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $messageId,
                    ]);
                    if ($t instanceof CoroutineNumberExceedException) {
                        Coroutine::sleep(60);
                    }
                    if ($this->queue->isReserved($messageId)) {
                        $this->queue->release($messageId, 60);
                    }
                }

                $limit = $this->options['memory_limit'] ?? 96;
                if ($limit <= EnvironmentHelper::getCurrentMemoryUsage()) {
                    $this->logger->info("Memory exceeded, worker:{$this->name} will reload later.");
                    $this->working = false;
                }
            }
        });
    }
}
