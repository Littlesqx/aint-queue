<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue;

interface JobMiddlewareInterface
{
    /**
     * Handle current middleware.
     *
     * @param JobInterface $job
     * @param \Closure                $next
     *
     * @return mixed
     */
    public function handle(JobInterface $job, \Closure $next);
}
