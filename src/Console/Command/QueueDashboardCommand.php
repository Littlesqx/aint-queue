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

use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueDashboardCommand extends AbstractCommand
{
    protected static $defaultName = 'queue:dashboard';

    protected function configure()
    {
        $this->setDescription('Start http server for dashboard.')
            ->setHelp('This Command allows you to start http server for dashboard.')
            ->addOption('channel', 't', InputOption::VALUE_REQUIRED, 'The channel of queue.', 'default')
            ->addOption('addr', 'a', InputOption::VALUE_REQUIRED, 'The listen address([host]:[port]) for server.', '127.0.0.1:8001');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $params = explode(':', $input->getOption('addr'));
        if (2 !== count($params)) {
            throw new InvalidArgumentException('The addr option must be a valid address, for example: 127.0.0.1:8001');
        }

        [$host, $port] = $params;

        Coroutine\run(function () use ($host, $port) {
            $server = new Server($host, (int) $port, false);

            $dashboardHtmlCached = null;
            $server->handle('/', function (Request $request, Response $response) use (&$dashboardHtmlCached) {
                if (null === $dashboardHtmlCached) {
                    $fp = fopen(__DIR__.'/../../Resource/dashboard.html', 'r');
                    $dashboardHtmlCached = Coroutine::fread($fp);
                }
                $response->end($dashboardHtmlCached);
            });

            $server->handle('/api/queue_status', function (Request $request, Response $response) {
                [$waiting, $reserved, $delayed, $done, $failed, $total] = $this->manager->getQueue()->status();
                $status = compact('waiting', 'reserved', 'failed', 'delayed', 'done', 'total');

                $pie = [];
                foreach ($status as $tag => $item) {
                    if ('total' !== $tag) {
                        $pie[] = [
                            'status' => $tag,
                            'value' => $item,
                        ];
                    }
                }

                $line = array_merge(['time' => date('Y-m-d H:i:s')], $status);

                $response->header('content-type', 'application/json');
                $response->end(json_encode(compact('pie', 'line')));
            });

            $this->manager->getLogger()->info("Http server for dashboard is started at http://{$host}:{$port}");

            if (!$server->start()) {
                $this->manager->getLogger()
                    ->error(sprintf(
                        'Http server for dashboard failed to start: err_msg=%s, code=%s',
                        $server->errMsg, $server->errCode
                    ));
            }
        });
    }
}
