<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\listener;

use think\App;
use think\exception\HttpResponseException;
use think\Response;

class CheckRequestCache
{
    /**
     * 设置当前地址的请求缓存
     * @access public
     * @return void
     */
    public function handle($event, App $app): void
    {
        $cache = $app->request->cache();

        if ($cache) {
            list($key, $expire, $tag) = $cache;

            if (strtotime($this->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $this->server('REQUEST_TIME')) {
                // 读取缓存
                $response = Response::create()->code(304);
                throw new HttpResponseException($response);
            } elseif ($app['cache']->has($key)) {
                list($content, $header) = $app['cache']->get($key);

                $response = Response::create($content)->header($header);
                throw new HttpResponseException($response);
            }
        }
    }
}
