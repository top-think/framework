<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\route;

use think\Route;

/**
 * 资源路由注册类
 */
class ResourceRegister
{
    /**
     * 延迟解析
     * @var bool
     */
    protected $lazy;

    /**
     * 延迟解析
     * @var bool
     */
    protected $router;

    /**
     * 延迟解析
     * @var bool
     */
    protected $parent;

    /**
     * 延迟解析
     * @var bool
     */
    protected $name;

    /**
     * 延迟解析
     * @var bool
     */
    protected $route;

    /**
     * 是否注册过
     * @var bool
     */
    protected $registered = false;

    /**
     * 路由参数
     * @var array
     */
    protected $option = [];

    /**
     * 架构函数
     * @access public
     * @param  Route         $router     路由对象
     * @param  RuleGroup     $parent     上级对象
     * @param  string        $name       资源名称
     * @param  string        $route      路由地址
     * @param  array         $rest       资源定义
     * @param  bool          $lazy     延迟解析
     */
    public function __construct(Route $router, RuleGroup $parent = null, string $name = '', string $route = '', array $rest = [], bool $lazy = false)
    {
        $this->router   = $router;
        $this->parent   = $parent;
        $this->name     = $name;
        $this->route    = $route;
        $this->lazy     = $lazy;
        
        $this->option['rest'] = $rest;
    }

    /**
     * 设置资源允许
     * @access public
     * @param  array $only 资源允许
     * @return $this
     */
    public function only(array $only)
    {
        $this->option['only'] =  $only;
        return $this;
    }

    /**
     * 设置资源排除
     * @access public
     * @param  array $except 排除资源
     * @return $this
     */
    public function except(array $except)
    {
        $this->option['except'] = $except;
        return $this;
    }

    /**
     * 设置资源路由的变量
     * @access public
     * @param  array $vars 资源变量
     * @return $this
     */
    public function vars(array $vars)
    {
        $this->option['var'] = $vars;
        return $this;
    }

    /**
     * 绑定资源验证
     * @access public
     * @param  array|string $name 资源类型或者验证信息
     * @param  array|string $validate 验证信息
     * @return $this
     */
    public function withValidate($name, $validate = [])
    {
        if (is_array($name)) {
            $this->option['validate'] = $name;
        } else {
            $this->option['validate'][$name] = $validate;
        }

        return $this;
    }

    /**
     * 绑定资源模型
     * @access public
     * @param  array|string $name 资源类型或者模型绑定
     * @param  array|string $model 模型绑定
     * @return $this
     */
    public function withModel($name, $model = [])
    {
        if (is_array($name)) {
            $this->option['model'] = $name;
        } else {
            $this->option['model'][$name] = $model;
        }

        return $this;
    }

    /**
     * 绑定资源中间件
     * @access public
     * @param  array|string $name 资源类型或者中间件定义
     * @param  array|string $middleware 中间件定义
     * @return $this
     */
    public function withMiddleware($name, $middleware = [])
    {
        if (is_array($name)) {
            $this->option['middleware'] = $name;
        } else {
            $this->option['middleware'][$name] = $middleware;
        }

        return $this;
    }

    /**
     * rest方法定义和修改
     * @access public
     * @param  array|string  $name 方法名称
     * @param  array|bool    $resource 资源
     * @return $this
     */
    public function rest($name, $resource = [])
    {
        if (is_array($name)) {
            $this->option['rest'] = $resource ? $name : array_merge($this->option['rest'], $name);
        } else {
            $this->option['rest'][$name] = $resource;
        }

        return $this;
    }

    /**
     * 注册变量规则
     * @access public
     * @param  array $pattern 变量规则
     * @return $this
     */
    public function pattern(array $pattern)
    {
        $this->option['pattern'] = $pattern;

        return $this;
    }

    /**
     * 注册资源路由
     * @access public
     * @return void
     */
    public function register()
    {
        $this->registered = true;

        $resource = new Resource($this->router, $this->parent, $this->name, $this->route, $this->option['rest']);

        foreach (['vars', 'only', 'except', 'model', 'validate', 'middleware', 'rest', 'pattern'] as $name) {
            if (isset($this->option[$name])) {
                $resource->$name($this->option[$name]);
            }
        }

        if (!$this->lazy) {
            $resource->parseGroupRule($this->name);
        }
    }

    public function __destruct()
    {
        if (!$this->registered) {
            $this->register();
        }
    }
}
