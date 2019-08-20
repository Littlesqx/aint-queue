<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Exception\RuntimeException;
use Littlesqx\AintQueue\Helper\EnvironmentHelper;
use Symfony\Component\Process\Process;

class SingleWorker implements WorkerInterface
{
    /**
     * Deliver an task into current worker(blocking).
     *
     * @param QueueInterface $queue
     * @param int $messageId
     * @param \Closure|JobInterface $message
     *
     * @return mixed|void
     * @throws RuntimeException
     */
    public function deliver(QueueInterface $queue, $messageId, $message)
    {
        $entry = EnvironmentHelper::getAppBinary();
        if (null === $entry) {
            throw new RuntimeException('Fail to get app entry file.');
        }

        $cmd = [
            EnvironmentHelper::getPhpBinary(),
            $entry,
            'queue:run',
            "--id={$messageId}",
            "--topic={$queue->getTopic()}",
        ];

        $process = new Process($cmd);

        // set timeout
        if ($message instanceof JobInterface && ($ttr = $message->getTtr()) > 0) {
            $process->setTimeout($ttr);
        }

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                fwrite(\STDERR, $buffer);
            } else {
                fwrite(\STDOUT, $buffer);
            }
        });
    }
}
