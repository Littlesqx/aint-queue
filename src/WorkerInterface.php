<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

interface WorkerInterface
{
    /**
     * deliver an task into current worker.
     *
     * @param QueueInterface $queue
     * @param int $messageId
     * @param \Closure|JobInterface $message
     *
     * @return mixed
     */
    public function deliver(QueueInterface $queue, $messageId, $message);
}
