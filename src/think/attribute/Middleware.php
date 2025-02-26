<?php

namespace think\attribute;

/**
 * 设置中间件
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * 中间件
     * @var string|array
     */
    public string|array $middleware;

    /**
     * 仅生效的操作
     * @var string[]
     */
    public array $only;

    /**
     * 排除的操作
     * @var string[]
     */
    public array $except;

    /**
     * 设置中间件
     * @param string|array $middleware 中间件
     * @param string[]     $only       仅生效的操作
     * @param string[]     $except     排除的操作
     */
    public function __construct(string|array $middleware, array $only = [], array $except = [])
    {
        $this->middleware = $middleware;
        $this->only = $only;
        $this->except = $except;
    }
}
