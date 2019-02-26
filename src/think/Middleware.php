<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Slince <taosikai@yeah.net>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

use InvalidArgumentException;
use LogicException;
use think\exception\HttpResponseException;

class Middleware
{
    /**
     * 中间件执行队列
     * @var array
     */
    protected $queue = [];

    /**
     * 配置
     * @var array
     */
    protected $config = [
        'default_namespace' => 'app\\http\\middleware\\',
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    public static function __make(Config $config)
    {
        return new static($config->get('middleware'));
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 导入中间件
     * @access public
     * @param  array  $middlewares
     * @param  string $type  中间件类型
     * @return void
     */
    public function import(array $middlewares = [], string $type = 'route'): void
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware, $type);
        }
    }

    /**
     * 注册中间件
     * @access public
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     * @return void
     */
    public function add($middleware, string $type = 'route'): void
    {
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware, $type);

        if ($middleware) {
            $this->queue[$type][] = $middleware;
        }
    }

    /**
     * 注册控制器中间件
     * @access public
     * @param  mixed  $middleware
     * @return void
     */
    public function controller($middleware): void
    {
        $this->add($middleware, 'controller');
    }

    /**
     * 移除中间件
     * @access public
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     */
    public function unshift($middleware, string $type = 'route')
    {
        if (is_null($middleware)) {
            return;
        }

        $middleware = $this->buildMiddleware($middleware, $type);

        if ($middleware) {
            array_unshift($this->queue[$type], $middleware);
        }
    }

    /**
     * 获取注册的中间件
     * @access public
     * @param  string $type  中间件类型
     */
    public function all(string $type = 'route'): array
    {
        return $this->queue[$type] ?? [];
    }

    /**
     * 中间件调度
     * @access public
     * @param  Request  $request
     * @param  string   $type  中间件类型
     */
    public function dispatch(Request $request, string $type = 'route')
    {
        return call_user_func($this->resolve($type), $request);
    }

    /**
     * 解析中间件
     * @access protected
     * @param  mixed  $middleware
     * @param  string $type  中间件类型
     * @return array
     */
    protected function buildMiddleware($middleware, string $type = 'route'): array
    {
        if (is_array($middleware)) {
            list($middleware, $param) = $middleware;
        }

        if ($middleware instanceof \Closure) {
            return [$middleware, $param ?? null];
        }

        if (!is_string($middleware)) {
            throw new InvalidArgumentException('The middleware is invalid');
        }

        if (false === strpos($middleware, '\\')) {
            if (isset($this->config[$middleware])) {
                $middleware = $this->config[$middleware];
            } else {
                $middleware = $this->config['default_namespace'] . $middleware;
            }
        }

        if (is_array($middleware)) {
            $this->import($middleware, $type);
            return [];
        }

        if (strpos($middleware, ':')) {
            list($middleware, $param) = explode(':', $middleware, 2);
        }

        return [[Container::pull($middleware), 'handle'], $param ?? null];
    }

    protected function resolve(string $type = 'route')
    {
        return function (Request $request) use ($type) {
            $middleware = array_shift($this->queue[$type]);

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
