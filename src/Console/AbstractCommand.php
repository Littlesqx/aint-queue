<?php

/**
 * This file is part of aint-queue.
 *
 * Copyright © 2012 - 2019 Xiaoman. All Rights Reserved.
 *
 * Created by Shengqian <shengqian@xiaoman.cn>, on 2019/08/20.
 */

namespace Littlesqx\AintQueue\Console;

use Littlesqx\AintQueue\Driver\Redis\Queue;
use Littlesqx\AintQueue\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    /**
     * @var Manager
     */
    protected $manager;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $topic = $input->getOption('topic');

        $this->manager = new Manager(new Queue($topic), []);
    }

}
