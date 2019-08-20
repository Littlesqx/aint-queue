<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/19.
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
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Job\'s ID.'. 'test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $messageId = $input->getOption('id');

        $this->manager->executeJob($messageId);

        return 0;
    }
}
