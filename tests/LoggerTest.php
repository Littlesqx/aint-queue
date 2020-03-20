<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Littlesqx\AintQueue\Logger\DefaultLogger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    /**
     * @test
     */
    public function default_logger_can_work()
    {
        $logger = new DefaultLogger();
        $expectedLog = 'This is a test message, id = '.uniqid();
        $logger->info($expectedLog);

        // get the latest line

        $latestLine = '';
        $fp = @fopen('/tmp/aint-queue-default.log', 'r');

        if ($fp) {
            $i = -2;
            while (1) {
                $r = fseek($fp, $i--, SEEK_END);

                if (-1 === $r) {
                    fseek($fp, $i + 2, SEEK_END);
                    break;
                }

                $tmp = fgetc($fp);
                if ("\n" === $tmp) {
                    break;
                }
            }
            $latestLine = fgets($fp);
        }

        $this->assertStringContainsString($expectedLog, $latestLine);
    }
}
