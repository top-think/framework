<?php

namespace think\tests;

use Mockery as m;
use Mockery\MockInterface;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Config;
use think\Console;
use think\Exception;
use think\Http;
use think\Request;
use think\Response;
use think\Route;

class HttpTest extends TestCase
{
    /** @var App|MockInterface */
    protected $app;

    /** @var Http|MockInterface */
    protected $http;

    protected function tearDown(): void
    {
        m::close();
    }

    protected function setUp()
    {
        $this->app = m::mock(App::class)->makePartial();

        $this->http = m::mock(Http::class, [$this->app])->shouldAllowMockingProtectedMethods()->makePartial();
    }

    protected function prepareApp($request, $response)
    {
        $this->app->shouldReceive('instance')->once()->with('request', $request);
        $this->app->shouldReceive('initialized')->once()->andReturnFalse();
        $this->app->shouldReceive('initialize')->once();
        $this->app->shouldReceive('get')->with('request')->andReturn($request);

        $route = m::mock(Route::class);

        $route->shouldReceive('dispatch')->withArgs(function ($req, $withRoute) use ($request) {
            if ($withRoute) {
                $withRoute();
            }
            return $req === $request;
        })->andReturn($response);

        $route->shouldReceive('config')->with('route_annotation')->andReturn(true);

        $this->app->shouldReceive('get')->with('route')->andReturn($route);

        $console = m::mock(Console::class);

        $console->shouldReceive('call');

        $this->app->shouldReceive('get')->with('console')->andReturn($console);
    }

    public function testRun()
    {
        $root = vfsStream::setup('rootDir', null, [
            'app'   => [
                'controller'     => [],
                'middleware.php' => '<?php return [];',
            ],
            'route' => [
                'route.php' => '<?php return [];',
            ],
        ]);

        $this->app->shouldReceive('getBasePath')->andReturn($root->getChild('app')->url() . DIRECTORY_SEPARATOR);
        $this->app->shouldReceive('getRootPath')->andReturn($root->url() . DIRECTORY_SEPARATOR);

        $request  = m::mock(Request::class)->makePartial();
        $response = m::mock(Response::class)->makePartial();

        $this->prepareApp($request, $response);

        $this->assertEquals($response, $this->http->run($request));
    }

    /**
     * @param $auto
     * @dataProvider multiAppRunProvider
     */
    public function testMultiAppRun($request, $auto, $index)
    {
        $root = vfsStream::setup('rootDir', null, [
            'app'   => [
                'middleware.php' => '<?php return [];',
            ],
            'route' => [
                'route.php' => '<?php return [];',
            ],
        ]);

        $config = m::mock(Config::class)->makePartial();

        $config->shouldReceive('get')->with('app.auto_multi_app', false)->andReturn($auto);

        $config->shouldReceive('get')->with('app.domain_bind', [])->andReturn([
            'www.domain.com' => 'app1',
            'app2'           => 'app2',
        ]);

        $config->shouldReceive('get')->with('app.app_map', [])->andReturn([
            'some1' => 'app1',
            'some2' => function ($app) {
                $this->assertEquals($this->app, $app);
            },
        ]);

        $this->app->shouldReceive('get')->with('config')->andReturn($config);

        $this->app->shouldReceive('getBasePath')->andReturn($root->getChild('app')->url() . DIRECTORY_SEPARATOR);
        $this->app->shouldReceive('getRootPath')->andReturn($root->url() . DIRECTORY_SEPARATOR);

        $response = m::mock(Response::class)->makePartial();

        $this->prepareApp($request, $response);

        $this->assertTrue($this->http->isMulti());

        if ($index === 4) {
            $this->http->shouldReceive('reportException')->once();

            $this->http->shouldReceive('renderException')->once()->andReturn($response);
        }

        $this->assertEquals($response, $this->http->run($request));
    }

    public function multiAppRunProvider()
    {
        $request1 = m::mock(Request::class)->makePartial();
        $request1->shouldReceive('subDomain')->andReturn('www');
        $request1->shouldReceive('host')->andReturn('www.domain.com');

        $request2 = m::mock(Request::class)->makePartial();
        $request2->shouldReceive('subDomain')->andReturn('app2');
        $request2->shouldReceive('host')->andReturn('app2.domain.com');

        $request3 = m::mock(Request::class)->makePartial();
        $request3->shouldReceive('pathinfo')->andReturn('some1/a/b/c');

        $request4 = m::mock(Request::class)->makePartial();
        $request4->shouldReceive('pathinfo')->andReturn('app1/a/b/c');

        return [
            [$request1, true, 1],
            [$request2, true, 2],
            [$request3, true, 3],
            [$request4, true, 4],
            [$request1, false, 5],
        ];
    }

    public function testRunWithException()
    {
        $request  = m::mock(Request::class);
        $response = m::mock(Response::class);

        $this->app->shouldReceive('instance')->once()->with('request', $request);

        $exception = new Exception();

        $this->http->shouldReceive('runWithRequest')->once()->with($request)->andThrow($exception);

        $this->http->shouldReceive('reportException')->once()->with($exception);

        $this->http->shouldReceive('renderException')->once()->with($request, $exception)->andReturn($response);

        $this->assertEquals($response, $this->http->run($request));
    }

}
