<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

declare(strict_types=1);

namespace Littlesqx\AintQueue\Console;

use Littlesqx\AintQueue\JobInterface;
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
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Job\'s ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $messageId = $input->getOption('id');

        if (empty($messageId)) {
            $output->writeln('The option \'id\' is required!');

            return;
        }

        $queue = $this->manager->getQueue();

        /** @var $job \Closure|JobInterface|null */
        [$id, $attempts, $job] = $queue->get($messageId);

        if (null === $job) {
            $output->writeln('The job is null.');

            return;
        }

        $output->writeln(sprintf('This job has been executed %s times', $attempts));

        is_callable($job) ? $job() : $job->handle();

        $queue->remove($id);
    }
}
