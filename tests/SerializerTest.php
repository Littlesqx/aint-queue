<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Littlesqx\AintQueue\Serializer\ClosureSerializer;
use Littlesqx\AintQueue\Serializer\PhpSerializer;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    /**
     * @test
     */
    public function php_serializer_can_throw_exception_when_serialize_not_object()
    {
        $serializer = new PhpSerializer();
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(1);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(false);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer([]);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer('string');
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(function () {});
    }

    /**
     * @test
     */
    public function php_serializer_can_serialize_object()
    {
        $serializer = new PhpSerializer();
        $object = new \stdClass();
        $object->name = 'anonymous';

        $serialized = $serializer->serializer($object);

        $this->assertIsString($serialized);

        $object = $serializer->unSerializer($serialized);

        $this->assertIsObject($object);
        $this->assertSame('anonymous', $object->name);
    }

    /**
     * @test
     */
    public function closure_serializer_can_throw_exception_when_serialize_not_closure()
    {
        $serializer = new ClosureSerializer();
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(1);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(false);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer([]);
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer('string');
        $this->expectException(\InvalidArgumentException::class);
        $serializer->serializer(new \stdClass());
    }

    /**
     * @test
     */
    public function closure_serializer_can_serialize_closure()
    {
        $serializer = new ClosureSerializer();
        $serialized = $serializer->serializer(function () {
            return 'I am a closure';
        });

        $this->assertIsString($serialized);

        $closure = $serializer->unSerializer($serialized);

        $this->assertIsCallable($closure);

        $closureRunResult = $closure();

        $this->assertSame('I am a closure', $closureRunResult);
    }
}
