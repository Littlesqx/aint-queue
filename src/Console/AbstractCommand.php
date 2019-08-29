<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Littlesqx\AintQueue\Console;

use Littlesqx\AintQueue\Driver\DriverFactory;
use Littlesqx\AintQueue\Driver\Redis\Queue;
use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Exception\InvalidDriverException;
use Littlesqx\AintQueue\Manager;
use Littlesqx\AintQueue\QueueInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * Initialize queue manager.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws InvalidArgumentException
     * @throws InvalidDriverException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $channel = $input->getOption('channel');

        $binPath = dirname($_SERVER['SCRIPT_FILENAME']);

        if (file_exists($binPath . '/../../config/aint-queue.php')) {
            $config = require $binPath . '/../../config/aint-queue.php';
        } else {
            $config = require __DIR__ . '/../Config/config.php';
        }

        if (!isset($config[$channel])) {
            throw new InvalidArgumentException(sprintf('[Error] The config of queue "%s" is not provided.', $channel));
        }

        $options = $config[$channel];

        $driverOptions = $options['driver'] ?? [];

        $driver = DriverFactory::make($driverOptions['class'] ?? '', $channel, $driverOptions['connection'] ?? []);

        $this->manager = new Manager($driver, $options);
    }
}
