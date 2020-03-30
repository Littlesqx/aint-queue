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
use Littlesqx\AintQueue\Serializer\ClosureSerializer;
use Littlesqx\AintQueue\Serializer\CompressingSerializer;
use Littlesqx\AintQueue\Serializer\PhpSerializer;
use PHPUnit\Framework\TestCase;
use Tests\Stub\DemoObject;

class SerializerTest extends TestCase
{
    /**
     * @test
     */
    public function php_serializer_can_throw_exception_when_serialize_not_object()
    {
        $serializer = new PhpSerializer();
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(1);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(false);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize([]);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize('string');
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(function () {});
    }

    /**
     * @test
     */
    public function php_serializer_can_serialize_object()
    {
        $serializer = new PhpSerializer();
        $object = new \stdClass();
        $object->name = 'anonymous';

        $serialized = $serializer->serialize($object);

        $this->assertIsString($serialized);

        $object = $serializer->unSerialize($serialized);

        $this->assertIsObject($object);
        $this->assertSame('anonymous', $object->name);
    }

    /**
     * @test
     */
    public function closure_serializer_can_throw_exception_when_serialize_not_closure()
    {
        $serializer = new ClosureSerializer();
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(1);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(false);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize([]);
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize('string');
        $this->expectException(InvalidArgumentException::class);
        $serializer->serialize(new \stdClass());
    }

    /**
     * @test
     */
    public function closure_serializer_can_serialize_closure()
    {
        $serializer = new ClosureSerializer();
        $serialized = $serializer->serialize(function () {
            return 'I am a closure';
        });

        $this->assertIsString($serialized);

        $closure = $serializer->unSerialize($serialized);

        $this->assertIsCallable($closure);

        $closureRunResult = $closure();

        $this->assertSame('I am a closure', $closureRunResult);
    }

    /**
     * @test
     */
    public function compressing_serializer_can_serialize_object()
    {
        $compressingSerializer = new CompressingSerializer();
        $phpSerializer = new PhpSerializer();
        $smallObject = new DemoObject([]);
        $compressingSerialized = $compressingSerializer->serialize($smallObject);
        $phpSerialized = $phpSerializer->serialize($smallObject);
        $this->assertSame($phpSerialized, substr($compressingSerialized, 1));
        $unSerialize = $compressingSerializer->unSerialize($compressingSerialized);
        $this->assertInstanceOf(DemoObject::class, $unSerialize);

        $bigObject = new DemoObject([
            str_repeat('a', 256),
        ]);
        $phpSerialized = $phpSerializer->serialize($bigObject);
        $this->assertNotSame($phpSerialized, substr($compressingSerialized, 1));
        $unSerialize = $compressingSerializer->unSerialize($compressingSerialized);
        $this->assertInstanceOf(DemoObject::class, $unSerialize);
    }
}
