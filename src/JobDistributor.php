<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/25.
 */

namespace Littlesqx\AintQueue;

use Littlesqx\AintQueue\Worker\CoroutineWorker;
use Littlesqx\AintQueue\Worker\ProcessPoolWorker;
use Littlesqx\AintQueue\Worker\ProcessWorker;

class JobDistributor
{
    /**
     * @var ProcessWorker
     */
    protected $processWorker;

    /**
     * @var ProcessPoolWorker
     */
    protected $processPoolWorker;

    /**
     * @var CoroutineWorker
     */
    protected $coroutineWorker;

    public function __construct(Manager $manager)
    {
        echo "__construct, " . getmypid() . "\n";
        $this->processWorker = new ProcessWorker($manager);
        $this->processWorker->start();
        $this->processPoolWorker = new ProcessPoolWorker($manager);
        $this->processPoolWorker->start();
        $this->coroutineWorker = new CoroutineWorker($manager);
    }

    /**
     * Dispatch job to executor.
     *
     * @param $messageId
     * @param $message*
     */
    public function dispatch($messageId, $message): void
    {
        if ($message instanceof CoJobInterface) {
            $this->coroutineWorker->receive($messageId);
        } elseif ($message instanceof AsyncJobInterface) {
            $this->processPoolWorker->receive($messageId);
        } else {
            $this->processWorker->receive($messageId);
        }
    }
}
