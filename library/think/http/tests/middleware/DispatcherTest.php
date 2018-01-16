<?php

namespace think\http\tests\middleware;

use PHPUnit\Framework\TestCase;
use think\http\middleware\Dispatcher;
use think\http\middleware\MissingResponseException;
use think\Request;
use think\Response;

class DispatcherTest extends TestCase
{
    public function testValidMiddleware()
    {
        $dispatcher = new Dispatcher();
        $dispatcher->add(function () {
        });
        $this->assertCount(1, $dispatcher->all());
        $this->expectException(\InvalidArgumentException::class);
        $dispatcher->add('foo middleware');
    }

    public function testAddAndInsert()
    {
        $middleware1 = function () {};
        $middleware2 = function () {};
        $dispatcher = new Dispatcher();
        $dispatcher->add($middleware1);
        $dispatcher->insert($middleware2);
        $this->assertSame([$middleware2, $middleware1], $dispatcher->all());
    }

    public function testDispatch()
    {
        $middleware1 = function ($request, $next) {
            return $next($request);
        };
        $middleware2 = function ($request) {
            return Response::create('hello world');
        };
        $dispatcher = new Dispatcher([$middleware1, $middleware2]);
        $response   = $dispatcher->dispatch(new Request());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('hello world', $response->getContent());
    }

    public function testDispatchWithoutResponse()
    {
        $middleware1 = function ($request, $next) {
            return $next($request);
        };
        $middleware2 = function ($request, $next) {
            return $next($request);
        };
        $dispatcher = new Dispatcher([$middleware1, $middleware2]);
        $this->expectException(MissingResponseException::class);
        $dispatcher->dispatch(new Request());
    }

    public function testDispatchWithBadResponse()
    {
        $middleware = function ($request, $next) {
            return 'invalid response';
        };
        $dispatcher = new Dispatcher($middleware);
        $this->expectException(\LogicException::class);
        $dispatcher->dispatch(new Request());
    }
}
