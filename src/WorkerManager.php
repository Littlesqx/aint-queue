<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Logger\LoggerInterface;
use Littlesqx\AintQueue\Worker\ConsumerWorker;
use Littlesqx\AintQueue\Worker\MonitorWorker;
use Littlesqx\AintQueue\Worker\PipeMessage;
use Swoole\Event;
use Swoole\Process;

class WorkerManager
{
    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var ConsumerWorker[]
     */
    protected $consumers = [];

    /**
     * @var MonitorWorker[]
     */
    protected $monitor = [];

    /**
     * @var int
     */
    protected $maxConsumerNum;

    /**
     * @var int
     */
    protected $minConsumerNum;

    /**
     * WorkerManager constructor.
     *
     * @param LoggerInterface $logger
     * @param QueueInterface  $queue
     * @param array           $options
     */
    public function __construct(QueueInterface $queue, LoggerInterface $logger, array $options = [])
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->options = $options;
    }

    /**
     * Start worker.
     */
    public function start(): void
    {
        // init monitor
        $this->createMonitor();

        // init consumer
        $this->maxConsumerNum = $this->options['consumer']['max_worker_number'] ?? 50;
        $this->minConsumerNum = $this->options['consumer']['min_worker_number'] ?? 4;
        for ($i = 0; $i < $this->minConsumerNum; ++$i) {
            $this->createConsumer();
        }

        // register signal
        Process::signal(SIGCHLD, function () {
            while ($ret = Process::wait(false)) {
                $pid = $ret['pid'] ?? -1;
                $reload = 1 !== (int) ($ret['code'] ?? 0);
                if (isset($this->consumers[$pid])) {
                    $this->logger->info("consumer#{$pid} for {$this->queue->getChannel()} is stopped.");
                    unset($this->consumers[$pid]);
                    $reload && $this->createConsumer();
                } elseif (isset($this->monitor[$pid])) {
                    $this->logger->info("monitor#{$pid} for {$this->queue->getChannel()} is stopped.");
                    Event::del($this->monitor[$pid]->getProcess()->pipe);
                    unset($this->monitor[$pid]);
                    $reload && $this->createMonitor();
                } else {
                    $this->logger->warning('Invalid pid, can not match worker, ret = '.json_encode($ret));
                }
            }
        });
    }

    /**
     * Reload all workers.
     */
    public function reload(): void
    {
        $workers = $this->monitor + $this->consumers;
        foreach ($workers as $pid => $worker) {
            Process::kill($pid, 0) && Process::kill($pid, SIGUSR1);
        }
    }

    /**
     * Stop all workers.
     */
    public function stop(): void
    {
        $workers = $this->monitor + $this->consumers;
        foreach ($workers as $pid => $worker) {
            Process::kill($pid, 0) && Process::kill($pid, SIGUSR2);
        }
    }

    /**
     * Create monitor worker.
     */
    protected function createMonitor(): void
    {
        $monitorWorker = new MonitorWorker($this->queue, $this->logger, $this->options);
        $pid = $monitorWorker->start();
        $this->monitor[$pid] = $monitorWorker;
        Event::add($monitorWorker->getProcess()->pipe, function () use ($monitorWorker) {
            $message = $monitorWorker->getProcess()->read(64 * 1024);
            $pipeMessage = new PipeMessage($message);

            $this->logger->info(sprintf('Received message from monitor, type = %s, payload = %s', $pipeMessage->type(), json_encode($pipeMessage->payload())));

            switch ($pipeMessage->type()) {
                case PipeMessage::MESSAGE_TYPE_CONSUMER_FLEX:
                    $this->flexWorkers();
                    break;
            }
        });
    }

    /**
     * Create a consumer worker.
     *
     * @return bool
     */
    protected function createConsumer(): bool
    {
        if (count($this->consumers) >= $this->maxConsumerNum) {
            return false;
        }

        $consumerWorker = new ConsumerWorker($this->queue, $this->logger, $this->options['consumer'] ?? []);
        $pid = $consumerWorker->start();
        $this->consumers[$pid] = $consumerWorker;

        return true;
    }

    /**
     * Release a worker (at random).
     *
     * @return bool
     */
    protected function releaseConsumer(): bool
    {
        $minWorker = $this->options['consumer']['min_worker_number'] ?? 4;
        if (count($this->consumers) <= $minWorker) {
            return false;
        }

        $selectedPid = array_rand($this->consumers);
        Process::kill($selectedPid, 0) && Process::kill($selectedPid, SIGUSR2);

        return true;
    }

    /**
     * Flex workers' number.
     */
    protected function flexWorkers(): void
    {
        $capacity = max($this->options['consumer']['capacity'] ?? 5, 1);

        try {
            [$waiting] = $this->queue->status();

            $healthWorkerNumber = max($this->minConsumerNum, min((int) ($waiting / $capacity), $this->maxConsumerNum));

            $differ = count($this->consumers) - $healthWorkerNumber;

            while (0 !== $differ) {
                // create more workers
                $differ < 0 && $this->createConsumer() && $differ++;
                // release idle workers
                $differ > 0 && $this->releaseConsumer() && $differ--;
            }
        } catch (\Throwable $t) {
            $this->logger->error('Fail to flex consumer worker number, error: '.$t->getMessage());
        }
    }
}
