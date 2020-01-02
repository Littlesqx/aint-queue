<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Console;

use Littlesqx\AintQueue\Console\Command\QueueClearCommand;
use Littlesqx\AintQueue\Console\Command\QueueReloadFailedCommand;
use Littlesqx\AintQueue\Console\Command\QueueStatusCommand;
use Littlesqx\AintQueue\Console\Command\WorkerListenCommand;
use Littlesqx\AintQueue\Console\Command\WorkerReloadCommand;
use Littlesqx\AintQueue\Console\Command\WorkerRunCommand;
use Littlesqx\AintQueue\Console\Command\WorkerStopCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct();

        $this->addCommands([
            new WorkerRunCommand(),
            new WorkerListenCommand(),
            new QueueStatusCommand(),
            new QueueClearCommand(),
            new WorkerStopCommand(),
            new WorkerReloadCommand(),
            new QueueReloadFailedCommand(),
        ]);
    }
}
