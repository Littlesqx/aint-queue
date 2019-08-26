<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueRunCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:run';

    protected function configure()
    {
        $this->setDescription('Run a job pop from the queue.')
            ->setHelp('This Command allows you to run a job at the head of the queue.')
            ->addOption('topic', 't', InputOption::VALUE_REQUIRED, 'The topic of queue.', 'default')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Job\'s ID.'.'test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $messageId = $input->getOption('id');

        $this->manager->executeJob($messageId);

        return 0;
    }
}
