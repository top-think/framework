<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\route;

use think\Route;

class Resource extends RuleGroup
{

    protected $route;

    // REST路由操作方法定义
    protected $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    /**
     * 架构函数
     * @access public
     * @param Route         $router     路由实例
     * @param string        $name       资源名称
     * @param string        $route      路由地址
     * @param array         $option     路由参数
     * @param array         $pattern    变量规则
     * @param array         $rest       资源定义
     */
    public function __construct(Route $router, $name, $route, $option = [], $pattern = [], $rest = [])
    {
        $this->router = $router;
        $this->name   = $name;
        $this->route  = $route;

        // 资源路由默认为完整匹配
        $option['complete_match'] = true;

        $this->pattern = $pattern;
        $this->option  = $option;

        $this->rest($rest);

        $this->buildRule($name, $option);
    }

    /**
     * 生成资源路由规则
     * @access public
     * @param string    $rule       路由规则
     * @param array     $option     路由参数
     * @return void
     */
    public function buildRule($rule, $option)
    {
        if (strpos($rule, '.')) {
            // 注册嵌套资源路由
            $array = explode('.', $rule);
            $last  = array_pop($array);
            $item  = [];

            foreach ($array as $val) {
                $item[] = $val . '/:' . (isset($option['var'][$val]) ? $option['var'][$val] : $val . '_id');
            }

            $rule = implode('/', $item) . '/' . $last;
        }

        // 注册分组
        $this->router->setRuleGroup($rule, $this);

        // 注册资源路由
        foreach ($this->rest as $key => $val) {
            if ((isset($option['only']) && !in_array($key, $option['only']))
                || (isset($option['except']) && in_array($key, $option['except']))) {
                continue;
            }

            if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);
            } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
            }

            $item           = ltrim($rule . $val[1], '/');
            $option['rest'] = $key;

            $this->rule($item, $this->route . '/' . $val[2], $val[0], $option);

        }
    }

    /**
     * 注册路由规则
     * @access public
     * @param string    $rule       路由规则
     * @param string    $route      路由地址
     * @param string    $type       请求类型
     * @param array     $option     路由参数
     * @param array     $pattern    变量规则
     * @return RuleItem
     */
    public function rule($rule, $route, $type = '*', $option = [], $pattern = [])
    {
        // 读取路由标识
        if (is_array($rule)) {
            list($name, $rule) = $rule;
        } elseif (is_string($route)) {
            $name = $route;
        }

        if (isset($name)) {
            // 设置路由标识 用于URL快速生成
            $vars = $this->router->parseVar($rule);
            $this->router->setName($name, $rule, $vars, $option);
        }

        $type = strtolower($type);

        $rule = new RuleItem($this->router, $this, $rule, $route, $type, $option, $pattern);

        // 添加到当前分组
        $this->addRule($rule, $type);

        return $rule;
    }

    /**
     * rest方法定义和修改
     * @access public
     * @param string|array  $name 方法名称
     * @param array|bool    $resource 资源
     * @return $this
     */
    public function rest($name, $resource = [])
    {
        if (is_array($name)) {
            $this->rest = $resource ? $name : array_merge($this->rest, $name);
        } else {
            $this->rest[$name] = $resource;
        }

        return $this;
    }

}
