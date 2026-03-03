<?php

namespace think\tests;

use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use think\Request;
use think\Route;

class UrlRouteTest extends TestCase
{
    use InteractsWithApp;

    /** @var Route|MockInterface */
    protected $route;

    protected function tearDown(): void
    {
        m::close();
    }

    protected function setUp(): void
    {
        $this->prepareApp();

        $this->route = new Route($this->app);
    }

    public function testUrlDispatch()
    {
        $controller = m::mock(FooClass::class);
        $controller->shouldReceive('index')->andReturn('bar');

        $this->app->shouldReceive('parseClass')->once()->with('controller', 'foo', '')
            ->andReturn($controller->mockery_getName());
        $this->app->shouldReceive('make')->with($controller->mockery_getName(), [], true)->andReturn($controller);

        $request  = $this->makeRequest('foo');
        $response = $this->route->dispatch($request);
        $this->assertEquals('bar', $response->getContent());
    }

    /**
     * 测试 Route::buildUrl()
     */
    public function testBuild()
    {
        $request  = $this->makeRequest('index/index.html');

        $this->app->request = $request;

        $urlBuild = new \think\route\Url($this->route, $this->app, '', []);
        $result = $urlBuild->build();

        $this->assertEquals('/index/index.html', $result);
    }

    /**
     * @param        $path
     * @param string $method
     * @param string $host
     * @return m\Mock|Request
     */
    protected function makeRequest($path, $method = 'GET', $host = 'localhost')
    {
        $request = m::mock(Request::class)->makePartial();
        $request->shouldReceive('host')->andReturn($host);
        $request->shouldReceive('pathinfo')->andReturn($path);
        $request->shouldReceive('url')->andReturn('/' . $path);
        $request->shouldReceive('method')->andReturn(strtoupper($method));
        return $request;
    }

}
