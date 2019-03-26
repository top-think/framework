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
use think\Container;
use think\exception\HttpException;
use think\Request;
use think\route\Rule;

class Url extends Controller
{

    public function __construct(Request $request, Rule $rule, $dispatch, array $param = [], int $code = null)
    {
        $this->request  = $request;
        $this->rule     = $rule;
        $this->app      = Container::pull('app');
        // 解析默认的URL规则
        $dispatch = $this->parseUrl($dispatch);

        parent::__construct($request, $rule, $dispatch, $param, $code);
    }

    /**
     * 解析URL地址
     * @access protected
     * @param  string $url URL
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
        list($controller, $path) = $this->parseController($path, $this->app->config->get('app.auto_multi_controller', false));

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
     * 解析访问控制器，支持多级控制器
     * @access public
     * @param  array $paths 地址
     * @param  bool $multi 是否多级控制器
     * @return array
     * @throws HttpException
     */
    private function parseController(array $paths, bool $multi = false) : array
    {
        $tempPaths   = \array_merge([], $paths);
        $controller  = !empty($tempPaths) ? array_shift($tempPaths) : null;
        if ($controller && !\preg_match('/^[A-Za-z][\w|\.]*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        if ($multi) {
            // 处理多级控制器
            $controllers = [];
            \array_push($controllers, $controller);
            // 控制器是否存在
            $exists = $this->exists($controller);
            do {
                if (!$exists) {
                    // 控制器不存在，取下一级路径
                    $nextPath = !empty($tempPaths) ? \array_shift($tempPaths) : null;
                    if ($nextPath && \preg_match('/^[A-Za-z][\w|\.]*$/', $nextPath)) {
                        \array_push($controllers, $nextPath);
                        $controller = join('.', $controllers);
                    } else {
                        // 下一级路径命名不正确, 压回数组, 退出循环
                        if (!empty($nextPath)) {
                            \array_unshift($tempPaths, $nextPath);
                        }
                        break;
                    }
                }
                // 控制器是否存在
                $exists = $this->exists($controller);
            } while (!$exists);

            // 多级控制器不存在，还原为单级控制器
            if (!$exists) {
                while (count($controllers) > 1) {
                    \array_unshift($tempPaths, \array_pop($controllers));
                }
                $controller = join('.', $controllers);
            }
        }

        return [$controller, $tempPaths];
    }

    /**
     * 检查URL是否已经定义过路由
     * @access protected
     * @param  array $route 路由信息
     * @return bool
     */
    protected function hasDefinedRoute(array $route): bool
    {
        list($controller, $action) = $route;

        // 检查地址是否被定义过路由
        $name = strtolower(App::parseName($controller, 1) . '/' . $action);

        $host   = $this->request->host(true);
        $method = $this->request->method();

        if ($this->rule->getRouter()->getName($name, $host, $method)) {
            return true;
        }

        return false;
    }

}
