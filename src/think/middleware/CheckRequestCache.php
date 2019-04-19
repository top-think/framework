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

namespace think\middleware;

use Closure;
use think\Cache;
use think\Request;
use think\Response;

/**
 * 请求缓存处理
 */
class CheckRequestCache
{
    protected $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * 设置当前地址的请求缓存
     * @access public
     * @param Request $request
     * @param         $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        if ($request->config('request_cache') && $request->isGet()) {
            $cache = $request->cache();

            if ($cache) {
                list($key, $expire, $tag) = $cache;

                if (strtotime($request->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $request->server('REQUEST_TIME')) {
                    // 读取缓存
                    return Response::create()->code(304);
                } elseif ($this->cache->has($key)) {
                    list($content, $header) = $this->cache->get($key);

                    return Response::create($content)->header($header);
                }
            }
        }

        $response = $next($request);

        if (isset($key) && 200 == $response->getCode() && $response->isAllowCache()) {
            $header                  = $response->getHeader();
            $header['Cache-Control'] = 'max-age=' . $expire . ',must-revalidate';
            $header['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
            $header['Expires']       = gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT';

            $this->cache->tag($tag)->set($key, [$response->getContent(), $header], $expire);
        }

        return $response;
    }
}
