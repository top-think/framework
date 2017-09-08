<?php
namespace tests\thinkphp\library\traits\controller;

use think\Config;
use think\Request;
use think\Response;
use think\response\Redirect;
use think\View;
use traits\controller\Jump;

class jumpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var testClassWithJump
     */
    protected $testClass;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var mixed
     */
    protected $originServerData;

    public function setUp()
    {
        $this->testClass = new testClassWithJump();
        $this->request   = Request::create('');

        $this->originServerData = Request::instance()->server();
    }

    public function tearDown()
    {
        Request::instance()->server($this->originServerData);
    }

    /**
     * @dataProvider provideTestSuccess
     */
    public function testSuccess($arguments, $expected, array $extra)
    {
        if (isset($extra['server'])) {
            $this->request->server($extra['server']);
        }

        $mock = $this->getMockBuilder(get_class($this->testClass))->setMethods(['getResponseType'])->getMock();
        $mock->expects($this->any())->method('getResponseType')->willReturn($extra['return']);

        try {
            call_user_func_array([$mock, 'success'], $arguments);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\\think\\exception\\HttpResponseException', $e);

            /** @var Response $response */
            $response = $e->getResponse();

            $this->assertInstanceOf('\\Think\\Response', $response);
            $this->assertEquals($expected['header'], $response->getHeader());
            $this->assertEquals($expected['data'], $response->getData());
        }
    }

    /**
     * @dataProvider provideTestError
     */
    public function testError($arguments, $expected, array $extra)
    {
        if (isset($extra['server'])) {
            $this->request->server($extra['server']);
        }

        $mock = $this->getMockBuilder(get_class($this->testClass))->setMethods(['getResponseType'])->getMock();
        $mock->expects($this->any())->method('getResponseType')->willReturn($extra['return']);

        try {
            call_user_func_array([$mock, 'error'], $arguments);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\\think\\exception\\HttpResponseException', $e);

            /** @var Response $response */
            $response = $e->getResponse();

            $this->assertInstanceOf('\\Think\\Response', $response);
            $this->assertEquals($expected['header'], $response->getHeader());
            $this->assertEquals($expected['data'], $response->getData());
        }
    }

    /**
     * @dataProvider provideTestResult
     */
    public function testResult($arguments, $expected, array $extra)
    {
        if (isset($extra['server'])) {
            $this->request->server($extra['server']);
        }

        $mock = $this->getMockBuilder(get_class($this->testClass))->setMethods(['getResponseType'])->getMock();
        $mock->expects($this->any())->method('getResponseType')->willReturn($extra['return']);

        try {
            call_user_func_array([$mock, 'result'], $arguments);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\\think\\exception\\HttpResponseException', $e);

            /** @var Response $response */
            $response = $e->getResponse();

            $this->assertInstanceOf('\\Think\\Response', $response);
            $this->assertEquals($expected['header'], $response->getHeader());
            $this->assertEquals($expected['data'], $response->getData());
        }
    }

    /**
     * @dataProvider provideTestRedirect
     */
    public function testRedirect($arguments, $expected)
    {
        try {
            call_user_func_array([$this->testClass, 'redirect'], $arguments);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\\think\\exception\\HttpResponseException', $e);

            /** @var Redirect $response */
            $response = $e->getResponse();

            $this->assertInstanceOf('\\think\\response\\Redirect', $response);
            $this->assertEquals($expected['url'], $response->getTargetUrl());
            $this->assertEquals($expected['code'], $response->getCode());
        }
    }

    public function testGetResponseType()
    {
        Request::instance()->server(['HTTP_X_REQUESTED_WITH' => null]);
        $this->assertEquals('html', $this->testClass->getResponseType());

        Request::instance()->server(['HTTP_X_REQUESTED_WITH' => true]);
        $this->assertEquals('html', $this->testClass->getResponseType());

        Request::instance()->server(['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest']);
        $this->assertEquals('json', $this->testClass->getResponseType());
    }

    public function provideTestSuccess()
    {
        $provideData = [];

        $arguments = ['', null, '', 3, []];
        $expected  = [
            'header' => [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            'data'   => View::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), [
                    'code' => 1,
                    'msg'  => '',
                    'data' => '',
                    'url'  => '/index.php/',
                    'wait' => 3,
                ])
        ];
        $provideData[] = [$arguments, $expected, ['server' => ['HTTP_REFERER' => null], 'return' => 'html']];

        $arguments = ['thinkphp', null, ['foo'], 4, ['Power-By' => 'thinkphp', 'Content-Type' => 'text/html; charset=gbk']];
        $expected  = [
            'header' => [
                'Content-Type' => 'text/html; charset=gbk',
                'Power-By' => 'thinkphp'
            ],
            'data'   => View::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), [
                    'code' => 1,
                    'msg'  => 'thinkphp',
                    'data' => ['foo'],
                    'url'  => 'http://www.thinkphp.cn',
                    'wait' => 4,
                ])
        ];
        $provideData[] = [$arguments, $expected, ['server' => ['HTTP_REFERER' => 'http://www.thinkphp.cn'], 'return' => 'html']];

        $arguments = ['thinkphp', 'index', ['foo'], 5, []];
        $expected  = [
            'header' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'data'   => [
                'code' => 1,
                'msg'  => 'thinkphp',
                'data' => ['foo'],
                'url'  => '/index.php/index.html',
                'wait' => 5,
                ]
        ];
        $provideData[] = [$arguments, $expected, ['server' => ['HTTP_REFERER' => null], 'return' => 'json']];

        return $provideData;
    }

    public function provideTestError()
    {
        $provideData = [];

        $arguments = ['', null, '', 3, []];
        $expected  = [
            'header' => [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            'data'   => View::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), [
                    'code' => 0,
                    'msg'  => '',
                    'data' => '',
                    'url'  => 'javascript:history.back(-1);',
                    'wait' => 3,
                ])
        ];
        $provideData[] = [$arguments, $expected, ['return' => 'html']];

        $arguments = ['thinkphp', 'http://www.thinkphp.cn', ['foo'], 4, ['Power-By' => 'thinkphp', 'Content-Type' => 'text/html; charset=gbk']];
        $expected  = [
            'header' => [
                'Content-Type' => 'text/html; charset=gbk',
                'Power-By' => 'thinkphp'
            ],
            'data'   => View::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), [
                    'code' => 0,
                    'msg'  => 'thinkphp',
                    'data' => ['foo'],
                    'url'  => 'http://www.thinkphp.cn',
                    'wait' => 4,
                ])
        ];
        $provideData[] = [$arguments, $expected, ['return' => 'html']];

        $arguments = ['thinkphp', '', ['foo'], 5, []];
        $expected  = [
            'header' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'data'   => [
                'code' => 0,
                'msg'  => 'thinkphp',
                'data' => ['foo'],
                'url'  => '',
                'wait' => 5,
            ]
        ];
        $provideData[] = [$arguments, $expected, ['return' => 'json']];

        return $provideData;
    }

    public function provideTestResult()
    {
        $provideData = [];

        $arguments = [null, 0, '', '', []];
        $expected  = [
            'header' => [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            'data' => [
                    'code' => 0,
                    'msg'  => '',
                    'time' => Request::create('')->server('REQUEST_TIME'),
                    'data' => null,
                ]
        ];
        $provideData[] = [$arguments, $expected, ['return' => 'html']];

        $arguments = [['foo'], 200, 'thinkphp', 'json', ['Power-By' => 'thinkphp']];
        $expected  = [
            'header' => [
                'Power-By' => 'thinkphp',
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'data'   => [
                'code' => 200,
                'msg'  => 'thinkphp',
                'time' => 1000,
                'data' => ['foo'],
            ]
        ];

        $provideData[] = [$arguments, $expected, ['server' => ['REQUEST_TIME' => 1000], 'return' => 'json']];

        return $provideData;
    }

    public function provideTestRedirect()
    {
        $provideData = [];

        $arguments = ['', [], 302, []];
        $expected  = [
            'code'=> 302,
            'url' => '/index.php/'
        ];
        $provideData[] = [$arguments, $expected, []];

        $arguments = ['index', 302, null, []];
        $expected  = [
            'code'=> 302,
            'url' => '/index.php/index.html'
        ];
        $provideData[] = [$arguments, $expected, []];

        $arguments = ['http://www.thinkphp.cn', 301, 302, []];
        $expected  = [
            'code'=> 301,
            'url' => 'http://www.thinkphp.cn'
        ];
        $provideData[] = [$arguments, $expected, []];

        return $provideData;
    }
}

class testClassWithJump
{
    use Jump {
        success as public;
        error as public;
        result as public;
        redirect as public;
        getResponseType as public;
    }
}
