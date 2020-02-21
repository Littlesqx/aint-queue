<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2020 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2020/02/21.
 */

namespace Littlesqx\AintQueue;


interface JobMiddlewareInterface
{
    /**
     * Handle current middleware.
     *
     * @param JobSnapshotterInterface $job
     * @param \Closure $next
     * @return mixed
     */
    public function handle(JobSnapshotterInterface $job, \Closure $next);
}