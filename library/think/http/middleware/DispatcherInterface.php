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

interface DispatcherInterface
{
    /**
     * 在队尾添加 middleware
     * @param callable $middleware
     * @return DispatcherInterface
     */
    public function add($middleware);

    /**
     * 在队前插入 middleware
     * @param callable $middleware
     * @return DispatcherInterface
     */
    public function insert($middleware);

    /**
     * 获取所有的middleware
     * @return array
     */
    public function all();

    /**
     * 处理 request 并返回 response
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request);
}
