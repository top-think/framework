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

namespace think\http\middleware;

use think\Container;
use think\Request;
use think\Response;

class Dispatcher implements DispatcherInterface
{
    protected $queue = [];

    public function __construct(array $middlewares = [])
    {
        $this->queue = $middlewares;
    }

    public function import(array $middlewares = [])
    {
        $this->queue = array_merge($this->queue, $middlewares);
    }

    /**
     * {@inheritdoc}
     */
    public function add($middleware)
    {
        $middleware = $this->makeInstance($middleware);

        $this->queue[] = $middleware;
    }

    /**
     * {@inheritdoc}
     */
    public function insert($middleware)
    {
        $middleware = $this->makeInstance($middleware);

        array_unshift($this->queue, $middleware);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->queue;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(Request $request)
    {
        return call_user_func($this->resolve(), $request);
    }

    protected function makeInstance($middleware)
    {
        if ($middleware instanceof \Closure) {
            return $middleware;
        }

        if (!is_string($middleware)) {
            throw new \InvalidArgumentException('The middleware is invalid');
        }

        $class = false === strpos($middleware, '\\') ? Container::get('app')->getNamespace() . '\\http\\middleware\\' . $middleware : $middleware;

        return [Container::get($class), 'handle'];
    }

    protected function resolve()
    {
        return function (Request $request) {
            $middleware = array_shift($this->queue);

            if (null !== $middleware) {
                $response = call_user_func($middleware, $request, $this->resolve());

                if (!$response instanceof Response) {
                    throw new \LogicException('The middleware must return Response instance');
                }

                return $response;
            } else {
                throw new MissingResponseException('The queue was exhausted, with no response returned');
            }
        };
    }

}
