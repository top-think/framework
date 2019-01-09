<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\listener;

use think\App;
use think\route\Dispatch;

class RouteCheck
{
    /**
     * 路由初始化（路由规则注册）
     * @access public
     * @return void
     */
    public function handle($event, App $app): void
    {
        // 加载路由定义
        if (is_dir($app->getRoutePath())) {
            $files = glob($app->getRoutePath() . DIRECTORY_SEPARATOR . '*.php');
            foreach ($files as $file) {
                include $file;
            }
        }

        if ($app->route->config('route_annotation')) {
            // 自动生成注解路由定义
            if ($app->isDebug()) {
                $suffix = $app->hasClassSuffix();
                $app->build->buildRoute($suffix);
            }

            $filename = $app->getRuntimePath() . 'build_route.php';

            if (is_file($filename)) {
                include $filename;
            }
        }

        // 路由检测
        $dispatch = $this->routeCheck($app)->init();
        $app->request->dispatch($dispatch);
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access protected
     * @return Dispatch
     */
    protected function routeCheck($app): Dispatch
    {
        // 检测路由缓存
        if (!$app->isDebug() && $app->config->get('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $app->config->get('route_cache_option');

            if ($option && $app->cache->connect($option)->has($routeKey)) {
                return $app->cache->connect($option)->get($routeKey);
            } elseif ($app->cache->has($routeKey)) {
                return $app->cache->get($routeKey);
            }
        }

        $path = $app->getRealPath();

        // 路由检测 返回一个Dispatch对象
        $dispatch = $app->route->check($path, $app->config->get('url_route_must'));

        if (!empty($routeKey)) {
            try {
                if ($option) {
                    $app->cache->connect($option)->tag('route_cache')->set($routeKey, $dispatch);
                } else {
                    $app->cache->tag('route_cache')->set($routeKey, $dispatch);
                }
            } catch (\Exception $e) {
                // 存在闭包的时候缓存无效
            }
        }

        return $dispatch;
    }

    protected function getRouteCacheKey(): string
    {
        if ($app->config->get('route_check_cache_key')) {
            $closure  = $app->config->get('route_check_cache_key');
            $routeKey = $closure($app->request);
        } else {
            $routeKey = md5($app->request->baseUrl(true) . ':' . $app->request->method());
        }

        return $routeKey;
    }
}
