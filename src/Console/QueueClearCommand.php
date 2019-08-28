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
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueClearCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:clear';

    protected function configure()
    {
        $this->setDescription('Clear the queue.')
            ->setHelp('This Command allows you to clear the queue.')
            ->addOption('topic', 't', InputOption::VALUE_REQUIRED, 'The topic of queue.', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $topic = $input->getOption('topic');

        $io = new SymfonyStyle($input, $output);

        if ($io->confirm("Are you sure to clear the $topic-queue?", false)) {
            $this->manager->getQueue()->clear();
        }
    }
}
