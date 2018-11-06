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

namespace think\route;

use think\Request;
use think\Route;

class AliasRule extends Domain
{
    protected $route;

    /**
     * 架构函数
     * @access public
     * @param  Route             $router 路由实例
     * @param  RuleGroup         $parent 上级对象
     * @param  string            $name   路由别名
     * @param  string            $route  路由绑定
     * @param  array             $option 路由参数
     */
    public function __construct(Route $router, RuleGroup $parent, string $name, string $route, array $option = [])
    {
        $this->router = $router;
        $this->parent = $parent;
        $this->name   = $name;
        $this->route  = $route;
        $this->option = $option;
    }

    /**
     * 检测域名路由
     * @access public
     * @param  Request      $request  请求对象
     * @param  string       $url      访问地址
     * @param  string       $depr     路径分隔符
     * @param  bool         $completeMatch   路由是否完全匹配
     * @return Dispatch|false
     */
    public function check(Request $request, string $url, string $depr = '/', bool $completeMatch = false)
    {
        if ($dispatch = $this->checkCrossDomain($request)) {
            // 允许跨域
            return $dispatch;
        }

        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        list($action, $bind) = array_pad(explode('|', $url, 2), 2, '');

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

        // 匹配后执行的行为
        $this->afterMatchGroup($request);

        if ($this->parent) {
            // 合并分组参数
            $this->mergeGroupOptions();
        }

        if (isset($this->option['ext'])) {
            // 路由ext参数 优先于系统配置的URL伪静态后缀参数
            $bind = preg_replace('/\.(' . $request->ext() . ')$/i', '', $bind);
        }

        $this->parseBindAppendParam($this->route);

        if (0 === strpos($this->route, '\\')) {
            // 路由到类
            return $this->bindToClass($bind, substr($this->route, 1));
        } else {
            // 路由到模块/控制器
            return $this->bindToController($bind, $this->route);
        }
    }

    /**
     * 设置允许的操作方法
     * @access public
     * @param  array      $action  操作方法
     * @return $this
     */
    public function allow(array $action = [])
    {
        return $this->setOption('allow', $action);
    }

    /**
     * 设置排除的操作方法
     * @access public
     * @param  array      $action  操作方法
     * @return $this
     */
    public function except(array $action = [])
    {
        return $this->setOption('except', $action);
    }

}
