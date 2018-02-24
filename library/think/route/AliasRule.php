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

namespace think\route;

class AliasRule extends Domain
{
    /**
     * 架构函数
     * @access public
     * @param  Route             $router 路由实例
     * @param  RuleGroup         $parent 上级对象
     * @param  string|array      $rule 路由规则
     * @param  string|\Closure   $route 路由地址
     * @param  array             $option 路由参数
     */
    public function __construct(Route $router, RuleGroup $parent, $name, $route, $option = [])
    {
        $this->router = $router;
        $this->parent = $parent;
        $this->name   = $name;
        $this->route  = $route;
        $this->option = $option;
    }

    public function check($request, $url, $depr = '/')
    {
        if ($dispatch = $this->checkCrossDomain($request)) {
            // 允许跨域
            return $dispatch;
        }

        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        $array = explode('|', $url);
        array_shift($array);
        $action = $array[1];

        if (isset($this->option['allow']) && !in_array($action, $this->option['allow'])) {
            // 允许操作
            return false;
        } elseif (isset($this->option['except']) && in_array($action, $this->option['except'])) {
            // 排除操作
            return false;
        }

        if (isset($this->option['method'][$action])) {
            $this->option['method'] = $this->option['method'][$action];
        }

        // 指定Response响应数据
        if (!empty($this->option['response'])) {
            Container::get('hook')->add('response_send', $this->option['response']);
        }

        // 开启请求缓存
        if (isset($this->option['cache']) && $request->isGet()) {
            $this->parseRequestCache($request, $this->option['cache']);
        }

        if ($this->parent) {
            // 合并分组参数
            $this->mergeGroupOptions();
        }

        if (!empty($this->option['append'])) {
            $request->route($this->option['append']);
        }

        $bind = implode('|', $array);

        if (0 === strpos($this->route, '\\')) {
            // 路由到类
            return $this->bindToClass($bind, substr($this->route, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // 路由到控制器类
            return $this->bindToController($bind, substr($this->route, 1), $depr);
        } else {
            // 路由到模块/控制器
            return $this->bindToModule($bind, $this->route, $depr);
        }
    }

    public function allow($action = [])
    {
        return $this->option('allow', $action);
    }

    public function except($action = [])
    {
        return $this->option('except', $action);
    }
}
