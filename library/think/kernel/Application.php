<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Slince <taosikai@yeah.net>
// +----------------------------------------------------------------------

namespace think\kernel;

use think\App;
use think\Container;
use think\http\middleware\Dispatcher as MiddlewareDispatcher;
use think\Request;
use think\Response;

class Application extends App
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * 应用是否已经启动
     * @var bool
     */
    protected $booted = false;

    /**
     * @var MiddlewareDispatcher
     */
    protected $middlewareDispatcher;

    /**
     * 处理请求
     * @param Request|null $request
     * @return Response
     */
    public function handle(Request $request = null)
    {
        if (false === $this->booted) {
            $this->boot();
        }
        if ($request === null) {
            $request = new Request();
        }
        $this->container->instance('request', $request);
        $response = $this->middlewareDispatcher->dispatch($request);
        return $response;
    }

    /**
     * 启动应用
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }
        $this->container = Container::getInstance();
        $this->initializeMiddleware();
    }

    /**
     * 添加自定义middleware
     * @param MiddlewareDispatcher $middlewareQueue
     */
    public function middleware(MiddlewareDispatcher $middlewareQueue)
    {
    }

    protected function initializeMiddleware()
    {
        $this->middlewareDispatcher = $this->container->get('middlewareDispatcher');
        $this->middleware($this->middlewareDispatcher);
        $this->middlewareDispatcher->add(function (){
            return parent::run();
        });
    }

    /**
     * handle 方法别名，6.0版本废除
     * @return Response
     * @deprecated 使用handle代替
     */
    public function run()
    {
        return $this->handle();
    }
}