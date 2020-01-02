<?php

declare(strict_types=1);

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueReloadFailedCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:reload-failed';

    protected function configure()
    {
        $this->setDescription('Reload all the failed jobs onto the waiting queue.')
            ->setHelp('This Command allows you to reload all the failed jobs onto the waiting queue.')
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Job\'s ID.'.'test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $failedJobs = $this->manager->getQueue()->getFailed();
        foreach ($failedJobs as $id => $payload) {
            $this->manager->getQueue()->reloadFailed($id);
        }
    }
}
