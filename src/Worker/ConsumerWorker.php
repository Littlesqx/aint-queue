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

use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\JobInterface;
use Swoole\Coroutine;

class ConsumerWorker extends AbstractWorker
{
    /**
     * Working for handle job in loop.
     *
     * @throws \Throwable
     */
    public function work(): void
    {
        @swoole_set_process_name(sprintf('aint-queue: consumer#%s', $this->queue->getChannel()));
        $this->logger->info(sprintf('consumer#%s for %s is started.', getmypid(), $this->queue->getChannel()));

        $this->init();

        Coroutine::create(function () {
            Coroutine::defer(function () {
                $this->queue->destroyConnection();
            });
            while ($this->working) {
                $messageId = $this->queue->pop();
                if (!$messageId) {
                    Coroutine::sleep($this->options['sleep_seconds'] ?? 1);
                    continue;
                }
                // If current worker is stopped,
                // the job popped will be push onto waiting queue again.
                if (!$this->working) {
                    $this->queue->release($messageId);
                    break;
                }
                try {
                    $this->handle($messageId);
                } catch (\Throwable $t) {
                    $this->logger->error(sprintf('Job exec error,  %s: %s', get_class($t), $t->getMessage()), [
                        'driver' => get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $messageId,
                    ]);
                    if ($this->queue->isReserved($messageId)) {
                        $this->queue->release($messageId, 60);
                    }
                }

                $limit = $this->options['memory_limit'] ?? 96;
                $used = memory_get_usage(true) / 1024 / 1024;
                if ($limit <= $used) {
                    $this->logger->info("Memory exceeded, consumer#{$this->pid} will be reloaded later.");
                    $this->working = false;
                    $this->workerReloadAble = true;
                }
            }
            if (!$this->workerReloadAble) {
                $this->process->exit(1);
            }
        });
    }

    /**
     * Handle job.
     *
     * @param int $messageId
     *
     * @throws \Throwable
     */
    protected function handle(int $messageId): void
    {
        $job = null;
        $id = $attempts = 0;

        try {
            /** @var $job \Closure|JobInterface */
            [$id, $attempts, $job] = $this->queue->get($messageId);

            if (null === $job) {
                throw new InvalidJobException('Job popped is null.');
            }
            is_callable($job) ? $job() : $job->handle();
            $this->queue->remove($id);
        } catch (\Throwable $t) {
            if ($job instanceof JobInterface && $job->canRetry($attempts, $t)) {
                $delay = max($job->retryAfter($attempts), 0);
                $this->queue->release($id, $delay);
            } else {
                $payload = [
                    'last_error' => get_class($t),
                    'last_error_message' => $t->getMessage(),
                    'attempts' => $attempts,
                ];
                $this->queue->failed($id, json_encode($payload));
                $job instanceof JobInterface && $job->failed($id, $payload);
            }
            $this->logger->error(get_class($t).': '.$t->getMessage(), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $id,
                'attempts' => $attempts ?? 0,
            ]);
        }
    }
}
