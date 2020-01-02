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
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueClearCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:clear';

    protected function configure()
    {
        $this->setDescription('Clear the queue.')
            ->setHelp('This Command allows you to clear the queue.')
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $channel = $input->getOption('channel');

        $io = new SymfonyStyle($input, $output);

        if ($io->confirm("Are you sure to clear the $channel-queue?", false)) {
            $this->manager->getQueue()->clear();
            $io->writeln('Success to clear!');
        }
    }
}
