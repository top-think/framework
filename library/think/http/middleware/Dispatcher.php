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

use think\Request;
use think\Response;

class Dispatcher implements DispatcherInterface
{
    protected $queue;

    public function __construct($middlewares = [])
    {
        $this->queue = (array) $middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function add($middleware)
    {
        $this->assertValid($middleware);
        $this->queue[] = $middleware;
    }

    /**
     * {@inheritdoc}
     */
    public function insert($middleware)
    {
        $this->assertValid($middleware);
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
        $requestHandler = $this->resolve();
        return call_user_func($requestHandler, $request);
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

    protected function assertValid($middleware)
    {
        if (!is_callable($middleware)) {
            throw new \InvalidArgumentException('The middleware is invalid');
        }
    }
}
