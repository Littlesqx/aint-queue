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

        [$waiting, $reserved, $delayed, $done, $failed, $total] = $this->manager->getQueue()->status();

        $waiting = implode('/', $waiting);

        $masterStatus = "The master-process of {$channel}-queue is ";
        if ($this->manager->isRunning()) {
            $masterStatus .= 'running!';
        } else {
            $masterStatus .= 'not running!';
        }
        $output->writeln("{$masterStatus}\nThe status of {$channel}-queue:");

        $table = new Table($output);
        $table->setStyle('box')
            ->setHeaders(['waiting', 'reserved', 'delayed', 'done', 'failed', 'total'])
            ->setRows([[$waiting, $reserved, "<comment>$delayed</comment>", "<info>$done</info>", "<comment>$failed</comment>", $total]]);

        $table->render();
    }
}
