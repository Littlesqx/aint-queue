<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Console;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueStatusCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:status';

    protected function configure()
    {
        $this->setDescription('Get the execute status of specific queue.')
            ->setHelp('This Command allows you to get the execute status of specific queue.')
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $channel = $input->getOption('channel');

        [$waiting, $delayed, $reserved, $done, $total] = $this->manager->getQueue()->status();

        $output->writeln("\nThe status of {$channel}-queue:\n");

        $table = new Table($output);
        $table->setStyle('box')
            ->setHeaders(['waiting', 'delayed', 'reserved', 'done', 'total'])
            ->setRows([["<comment>$waiting</comment>", "<comment>$delayed</comment>", $reserved, "<info>$done</info>", $total]]);

        $table->render();
    }
}
