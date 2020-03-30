<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Stub;

use Littlesqx\AintQueue\Compressable;

class DemoObject implements Compressable
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the compressing threshold (string length).
     *
     * @return int
     */
    public function getCompressingThreshold(): int
    {
        return 256;
    }
}
