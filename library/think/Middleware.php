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

namespace think;

use InvalidArgumentException;
use LogicException;
use think\exception\HttpResponseException;

class Middleware
{
    protected $queue = [];

    public function import(array $middlewares = [])
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add($middleware)
    {
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware);

        if ($middleware) {
            $this->queue[] = $middleware;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unshift($middleware)
    {
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware);

        if ($middleware) {
            array_unshift($this->queue, $middleware);
        }
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

    protected function buildMiddleware($middleware)
    {
        if (is_array($middleware)) {
            list($middleware, $param) = $middleware;
        }

        if ($middleware instanceof \Closure) {
            return [$middleware, isset($param) ? $param : null];
        }

        if (!is_string($middleware)) {
            throw new InvalidArgumentException('The middleware is invalid');
        }

        if (false === strpos($middleware, '\\')) {
            $value      = Container::get('config')->get('middleware.' . $middleware);
            $middleware = $value ?: Container::get('app')->getNamespace() . '\\http\\middleware\\' . $middleware;
        }

        if (is_array($middleware)) {
            return $this->import($middleware);
        }

        if (strpos($middleware, ':')) {
            list($middleware, $param) = explode(':', $middleware, 2);
        }

        return [[Container::get($middleware), 'handle'], isset($param) ? $param : null];
    }

    protected function resolve()
    {
        return function (Request $request) {
            $middleware = array_shift($this->queue);

            if (null === $middleware) {
                throw new InvalidArgumentException('The queue was exhausted, with no response returned');
            }

            list($call, $param) = $middleware;

            try {
                $response = call_user_func_array($call, [$request, $this->resolve(), $param]);
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();
            }

            if (!$response instanceof Response) {
                throw new LogicException('The middleware must return Response instance');
            }

            return $response;
        };
    }

}
