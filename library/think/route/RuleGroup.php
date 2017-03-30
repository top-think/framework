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

use think\Request;
use think\Route;
use think\route\Dispatch;
use think\route\dispatch\Url as UrlDispatch;

class RuleGroup extends Rule
{
    // 分组路由（包括子分组）
    protected $rules = [
        '*'       => [],
        'get'     => [],
        'post'    => [],
        'delete'  => [],
        'put'     => [],
        'head'    => [],
        'options' => [],
        'patch'   => [],
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

    /**
     * 检测分组路由
     * @access public
     * @param Request      $request  请求对象
     * @param string       $url      访问地址
     * @param string       $depr     路径分隔符
     * @return Dispatch
     */
    public function check($request, $url, $depr = '/')
    {
        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        // 获取当前路由规则
        $method = strtolower($request->method());
        $rules  = array_merge($this->rules['*'], $this->rules[$method]);

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
            if ($item instanceof RuleItem) {
                if ($item->isMiss()) {
                    $this->miss = $item;
                    continue;
                } elseif ($item->isAuto()) {
                    $this->auto = $item;
                    continue;
                }
            }

            $result = $item->check($request, $url, $depr);

            if (false !== $result) {
                return $result;
            }
        }

        if (isset($this->auto)) {
            // 自动解析URL地址
            $result = new UrlDispatch($this->auto->getRoute() . '/' . $url, ['depr' => $depr, 'auto_search' => false]);
        } elseif (isset($this->miss)) {
            // 未匹配所有路由的路由规则处理
            $result = $this->parseRule($request, '', $this->miss->getRoute(), $url, $this->miss->getOption());
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * 添加分组下的路由规则或者子分组
     * @access public
     * @param RuleGroup|RuleItem    $rule  路由规则
     * @param string                $method      请求类型
     * @return $this
     */
    public function addRule($rule, $method = '*')
    {
        $name = $rule->getName();

        if ($this->name && !($this instanceof Domain) && $rule instanceof RuleGroup) {
            $rule->name($this->name . '/' . $name);
        }

        if ($name) {
            $this->rules[$method][$name] = $rule;
        } else {
            $this->rules[$method][] = $rule;
        }

        return $this;
    }

    /**
     * 设置路由分组的前缀
     * @access public
     * @param string     $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        return $this->option('prefix', $prefix);
    }

}
