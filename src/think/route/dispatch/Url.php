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

namespace think\route\dispatch;

use think\App;
use think\exception\HttpException;
use think\route\Dispatch;

class Url extends Dispatch
{
    public function init()
    {
        // 解析默认的URL规则
        $result = $this->parseUrl($this->dispatch);

        return (new Controller($this->request, $this->rule, $result))->init();
    }

    public function exec()
    {}

    /**
     * 解析URL地址
     * @access protected
     * @param  string   $url URL
     * @return array
     */
    protected function parseUrl(string $url): array
    {
        $depr = $this->rule->config('pathinfo_depr');
        $bind = $this->rule->getRouter()->getDomainBind();

        if ($bind && preg_match('/^[a-z]/is', $bind)) {
            $bind = str_replace('/', $depr, $bind);
            // 如果有模块/控制器绑定
            $url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
        }

        list($path, $var) = $this->rule->parseUrlPath($url);
        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z][\w|\.]*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        $panDomain = $this->request->panDomain();
        if ($panDomain && $key = array_search('*', $var)) {
            // 泛域名赋值
            $var[$key] = $panDomain;
        }

        // 设置当前请求的参数
        $this->request->setRoute($var);

        // 封装路由
        $route = [$controller, $action];

        if ($this->hasDefinedRoute($route)) {
            throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
        }

        return $route;
    }

    /**
     * 检查URL是否已经定义过路由
     * @access protected
     * @param  array     $route  路由信息
     * @return bool
     */
    protected function hasDefinedRoute(array $route): bool
    {
        list($controller, $action) = $route;

        // 检查地址是否被定义过路由
        $name = strtolower(App::parseName($controller, 1) . '/' . $action);

        $host = $this->request->host(true);

        if ($this->rule->getRouter()->getName($name, $host)) {
            return true;
        }

        return false;
    }

}
