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
    // 分组路由（包括子分组）
    protected $rules = [
        '*'      => [],
        'get'    => [],
        'post'   => [],
        'delete' => [],
        'put'    => [],
        'head'   => [],
        'option' => [],
        'patch'  => [],
    ];

    // MISS路由
    protected $miss;

    // 自动路由
    protected $auto;

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
        $this->name    = trim($name, '/');
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

        // 获取当前路由规则
        $method = strtolower($request->method());
        $rules  = $this->rules['*'] + $this->rules[$method];

        if (isset($rules[$url])) {
            // 快速定位
            $item   = $rules[$url];
            $result = $item->check($request, $url, $depr);

            if (false !== $result) {
                return $result;
            }
        }

        // 遍历分组路由
        foreach ($rules as $key => $item) {
            if ('__miss__' == $item->getRule()) {
                $this->miss = $item;
                continue;
            } elseif ('__auto__' == $item->getRule()) {
                $this->auto = $item;
                continue;
            }
            $result = $item->check($request, $url, $depr);

            if (false !== $result) {
                return $result;
            }
        }

        if (isset($this->auto)) {
            // 自动解析URL地址
            return $this->router->parseUrl($this->auto->getRoute() . '/' . $url, $depr);
        } elseif (isset($this->miss)) {
            // 未匹配所有路由的路由规则处理
            return $this->parseRule($request, '', $this->miss->getRoute(), $url, $this->miss->getOption());
        } else {
            return false;
        }
    }

    public function addRule($rule, $method = '*')
    {
        $key = $rule->getRule();

        $this->rules[$method][$key] = $rule;

        return $this;
    }

    public function getRule()
    {
        return $this->name;
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
