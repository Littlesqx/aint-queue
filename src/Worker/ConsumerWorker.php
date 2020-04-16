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

use Illuminate\Pipeline\Pipeline;
use Littlesqx\AintQueue\Exception\InvalidJobException;
use Littlesqx\AintQueue\JobInterface;
use Swoole\Coroutine;

class ConsumerWorker extends AbstractWorker
{
    /**
     * @var int
     */
    protected $handled = 0;

    /**
     * @var Pipeline
     */
    protected $pipeline;

    /**
     * @var bool
     */
    protected $workerReloadAble = false;

    /**
     * Working for handle job in loop.
     *
     * @throws \Throwable
     */
    public function work(): void
    {
        @swoole_set_process_name(sprintf('aint-queue: consumer#%s', $this->queue->getChannel()));
        $this->logger->debug(sprintf('consumer#%s for %s is started.', getmypid(), $this->queue->getChannel()));

        $this->init();

        $this->pipeline = new Pipeline();

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
                    $this->logger->error(sprintf(
                        'Uncaptured exception[%s:%s] detected in %s::%d.',
                        get_class($t),
                        $t->getMessage(),
                        $t->getFile(),
                        $t->getLine()
                    ), [
                        'driver' => get_class($this->queue),
                        'channel' => $this->queue->getChannel(),
                        'message_id' => $messageId,
                    ]);
                    if ($this->queue->isReserved($messageId)) {
                        $this->queue->release($messageId, 60);
                    }
                }

                $maxHandle = $this->options['max_handle_number'] ?? 0;
                if ($maxHandle > 0 && ++$this->handled >= $maxHandle) {
                    $this->logger->debug("Max handle number exceeded, consumer#{$this->pid} will be reloaded later.");
                    $this->working = false;
                    $this->workerReloadAble = true;
                    continue;
                }
                $limit = $this->options['memory_limit'] ?? 96;
                $used = memory_get_usage(true) / 1024 / 1024;
                if ($limit <= $used) {
                    $this->logger->debug("Memory exceeded, consumer#{$this->pid} will be reloaded later.");
                    $this->working = false;
                    $this->workerReloadAble = true;
                }
            }
            if ($this->workerReloadAble) {
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
        try {
            /** @var $job \Closure|JobInterface */
            [, $attempts, $job] = $this->queue->get($messageId);

            if (empty($job)) {
                throw new InvalidJobException('Job popped is empty.');
            }
            is_callable($job) ? $job() : $this->pipeline->send($job)
                ->through($job->middleware())
                ->then(function (JobInterface $job) {
                    $job->handle();
                });
            $this->queue->remove($messageId);
        } catch (\Throwable $t) {
            $attempts = $attempts ?? 0;
            $payload = [
                'last_error' => get_class($t),
                'last_error_message' => $t->getMessage(),
                'attempts' => $attempts,
            ];
            if (!isset($job) || !$job instanceof JobInterface) {
                $job->failed($messageId, $payload);
            } else {
                if ($job->canRetry($attempts, $t)) {
                    $delay = max($job->retryAfter($attempts), 0);
                    $this->queue->release($messageId, $delay);
                } else {
                    $this->queue->failed($messageId, json_encode($payload));
                    $job->failed($messageId, $payload);
                }
            }
            $this->logger->error(sprintf(
                'Error when job executed: [%s]:[%s] detected in %s::%d.',
                get_class($t),
                $t->getMessage(),
                $t->getFile(),
                $t->getLine()
            ), [
                'driver' => get_class($this->queue),
                'channel' => $this->queue->getChannel(),
                'message_id' => $messageId,
                'attempts' => $attempts,
            ]);
        }
    }
}
