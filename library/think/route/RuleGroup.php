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
use think\Route;

class RuleGroup extends Rule implements IteratorAggregate
{

    // 分组名
    protected $name;
    // 分组路由（包括子分组）
    protected $rules = [];
    // 分组参数
    protected $option = [];
    // 分组变量规则
    protected $pattern = [];

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

    // 检测分组下的路由
    public function check($request, $url, $depr = '/')
    {
        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        // 检测静态路由

        // 检测分组路由
        $method = strtolower($request->method());

        foreach ($this->rules[$method] as $key => $item) {
            $result = $item->check($request, $url, $depr);

            if (false !== $result) {
                return $result;
            }
        }

        if (isset($this->auto)) {
            // 自动解析URL地址
            return $this->parseUrl($this->auto['route'] . '/' . $url, $depr);
        } elseif (isset($this->miss)) {
            // 未匹配所有路由的路由规则处理
            return $this->parseRule('', $this->miss['route'], $url, $this->miss['option']);
        }
    }

    public function addRule($rule, $method = '*')
    {
        $this->rules[$method][] = $rule;

        return $this;
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
}
