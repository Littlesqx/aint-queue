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

class QueueListenCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:listen';

    protected function configure()
    {
        $this->setDescription('listen the queue.')
            ->setHelp('This Command allows you to run a process to listen the queue.')
            ->addOption('topic', 't', InputOption::VALUE_REQUIRED, 'The topic of queue.', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // blocking
        $this->manager->listen();
    }
}