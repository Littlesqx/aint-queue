<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/23.
 */

namespace Littlesqx\AintQueue\Worker;

interface WorkerInterface
{
    /**
     * Run an task on current worker.
     *
     * @param int $messageId
     */
    public function receive($messageId): void;

    /**
     * Get worker name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get waiting task's queue name.
     *
     * @return string
     */
    public function getTaskQueueName(): string;

}