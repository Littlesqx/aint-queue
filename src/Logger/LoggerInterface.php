<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Logger;

interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    /**
     * Reset the log connection, if exist.
     */
    public function resetConnection(): void;
}
