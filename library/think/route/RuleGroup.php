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

use IteratorAggregate;

class RuleGroup extends Rule implements IteratorAggregate
{

    protected $name;
    protected $rules   = [];
    protected $option  = [];
    protected $pattern = [];
    protected $router;
    protected $request;

    /**
     * 架构函数
     * @access public
     * @param string      $rule     分组名称
     * @param array       $option     路由参数
     * @param array       $pattern     变量规则
     */
    public function __construct(Route $router, $name = '', $option = [], $pattern = [])
    {
        $this->router  = $router;
        $this->name    = $name;
        $this->option  = $option;
        $this->pattern = $pattern;
    }

    /**
     * Retrieve an external iterator
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator()
    {
        return new ArrayIterator($this->rules);
    }

    // 检测分组下的路由
    public function check($url, $depr = '/')
    {
        // 检测静态路由

        // 检测分组路由

        if (isset($auto)) {
            // 自动解析URL地址
            return $this->parseUrl($auto['route'] . '/' . $url, $depr);
        } elseif (isset($miss)) {
            // 未匹配所有路由的路由规则处理
            return $this->parseRule('', $miss['route'], $url, $miss['option']);
        }
    }

    public function addRule($rule, $method = '*')
    {
        $this->rules[$method][] = $rule;

        return $this;
    }

    public function option($option)
    {
        $this->option = $option;

        return $this;
    }

    public function pattern($pattern)
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getOption()
    {
        return $this->option;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getName()
    {
        return $this->name;
    }

}
