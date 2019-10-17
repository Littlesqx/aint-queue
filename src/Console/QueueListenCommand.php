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

use Littlesqx\AintQueue\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueListenCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:listen';

    protected function configure()
    {
        $this->setDescription('Listen the queue.')
            ->setHelp('This Command allows you to run a process to listen the queue.')
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->manager->isRunning()) {
            throw new RuntimeException(\sprintf('[Error] Listener for queue %s has been started.', $input->getOption('channel')));
        }
        // blocking
        $this->manager->listen();
    }
}
