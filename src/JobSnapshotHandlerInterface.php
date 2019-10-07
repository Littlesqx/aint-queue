<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/10/07.
 */

namespace Littlesqx\AintQueue;

interface JobSnapshotHandlerInterface
{
    /**
     * @param array $snapshot
     */
    public function handle(array $snapshot): void;
}