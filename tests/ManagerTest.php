<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Littlesqx\AintQueue\Exception\InvalidArgumentException;
use Littlesqx\AintQueue\Manager;
use PHPUnit\Framework\TestCase;
use Tests\Stub\DemoLogger;
use Tests\Stub\DemoQueue;

class ManagerTest extends TestCase
{
    /** @var Manager */
    protected $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new Manager(new DemoQueue(), ['pid_path' => './']);
    }

    /**
     * @test
     */
    public function master_is_running_can_work()
    {
        $this->assertFalse($this->manager->isRunning());
        file_put_contents($this->manager->getPidFile(), getmypid());
        $this->assertTrue($this->manager->isRunning());
    }

    /**
     * @test
     */
    public function can_get_queue()
    {
        $this->assertInstanceOf(DemoQueue::class, $this->manager->getQueue());
    }

    /**
     * @test
     */
    public function can_get_options()
    {
        $this->assertSame(['pid_path' => './'], $this->manager->getOptions());
    }

    /**
     * @test
     */
    public function setting_invalid_logger_will_throw_exception()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager = new Manager(new DemoQueue(), [
            'pid_path' => './',
            'logger' => get_class($this),
        ]);
    }

    /**
     * @test
     */
    public function can_set_logger()
    {
        $this->manager = new Manager(new DemoQueue(), [
            'pid_path' => './',
            'logger' => DemoLogger::class,
        ]);

        $this->assertInstanceOf(DemoLogger::class, $this->manager->getLogger());
    }

    protected function tearDown(): void
    {
        @unlink($this->manager->getPidFile());
        parent::tearDown();
    }
}
