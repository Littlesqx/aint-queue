<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Event;

interface HandlerInterface
{
    /**
     * Handle event.
     *
     * @param string $message
     * @param $error
     * @param $payload
     *
     * @return mixed
     */
    public function handle(string $message, $error, $payload);
}
