<?php

namespace think\tests;

use PHPUnit\Framework\TestCase;
use stdClass;
use think\Container;
use think\Exception;
use think\exception\ClassNotFoundException;

class Taylor
{
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public static function __make()
    {
        return new self('Taylor');
    }
}

class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testClosureResolution()
    {
        $container = new Container;

        Container::setInstance($container);

        $container->bind('name', function () {
            return 'Taylor';
        });

        $this->assertEquals('Taylor', $container->make('name'));

        $this->assertEquals('Taylor', Container::pull('name'));
    }

    public function testGet()
    {
        $container = new Container;

        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('class not exists: name');
        $container->get('name');

        $container->bind('name', function () {
            return 'Taylor';
        });

        $this->assertSame('Taylor', $container->get('name'));
    }

    public function testExist()
    {
        $container = new Container;

        $container->bind('name', function () {
            return 'Taylor';
        });

        $this->assertFalse($container->exists("name"));

        $container->make('name');

        $this->assertTrue($container->exists('name'));
    }

    public function testInstance()
    {
        $container = new Container;

        $container->bind('name', function () {
            return 'Taylor';
        });

        $this->assertEquals('Taylor', $container->get('name'));

        $object = new stdClass();

        $container->instance('name', $object);

        $this->assertEquals($object, $container->get('name'));
    }

    public function testBind()
    {
        $container = new Container;

        $object = new stdClass();

        $container->bind(['name' => Taylor::class]);

        $container->bind('name2', $object);

        $this->assertInstanceOf(Taylor::class, $container->get('name'));

        $this->assertSame($object, $container->get('name2'));
    }

    public function testAutoConcreteResolution()
    {
        $container = new Container;

        $taylor = $container->make(Taylor::class);

        $this->assertInstanceOf(Taylor::class, $taylor);
        $this->assertAttributeSame('Taylor', 'name', $taylor);
    }

    public function testGetAndSetInstance()
    {
        $this->assertInstanceOf(Container::class, Container::getInstance());

        $object = new stdClass();

        Container::setInstance($object);

        $this->assertSame($object, Container::getInstance());

        Container::setInstance(function () {
            return $this;
        });

        $this->assertSame($this, Container::getInstance());
    }

    public function testInvokeFunctionWithoutMethodThrowsException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('function not exists: ContainerTestCallStub()');
        $container = new Container;
        $container->invokeFunction('ContainerTestCallStub', []);
    }
}
