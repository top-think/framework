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

use think\Route;

class RuleItem extends Rule
{
    // 路由规则
    protected $name;
    // 路由地址
    protected $route;
    // 请求类型
    protected $method;

    /**
     * 架构函数
     * @access public
     * @param  Route             $router 路由实例
     * @param  RuleGroup         $group 路由所属分组对象
     * @param  string|array      $name 路由规则
     * @param  string            $method 请求类型
     * @param  string|\Closure   $route 路由地址
     * @param  array             $option 路由参数
     * @param  array             $pattern 变量规则
     */
    public function __construct(Route $router, RuleGroup $group, $name, $route, $method = '*', $option = [], $pattern = [])
    {
        $this->router  = $router;
        $this->parent  = $group;
        $this->route   = $route;
        $this->method  = $method;
        $this->option  = $option;
        $this->pattern = $pattern;

        $this->setRule($name);
    }

    /**
     * 路由规则预处理
     * @access public
     * @param  string      $rule     路由规则
     * @return void
     */
    public function setRule($rule)
    {
        if ('$' == substr($rule, -1, 1)) {
            // 是否完整匹配
            $rule = substr($rule, 0, -1);

            $this->option['complete_match'] = true;
        }

        $this->name($rule);
    }

    /**
     * 获取当前路由地址
     * @access public
     * @return mixed
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * 是否为MISS路由
     * @access public
     * @return bool
     */
    public function isMiss()
    {
        return '__miss__' == $this->name;
    }

    /**
     * 是否为自动路由
     * @access public
     * @return bool
     */
    public function isAuto()
    {
        return '__auto__' == $this->name;
    }

    /**
     * 检测路由
     * @access public
     * @param  Request      $request  请求对象
     * @param  string       $url      访问地址
     * @param  string       $depr     路径分隔符
     * @param  bool         $completeMatch   路由是否完全匹配
     * @return Dispatch
     */
    public function check($request, $url, $depr = '/', $completeMatch = false)
    {
        if ($this->parent && $groupName = $this->parent->getName()) {
            $this->name = $groupName . '/' . $this->name;
        }

        if ($dispatch = $this->checkCrossDomain($request)) {
            // 允许跨域
            return $dispatch;
        }

        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        // 合并分组参数
        $this->mergeGroupOptions();
        $option = $this->option;

        // 检查前置行为
        if (isset($option['before']) && false === $this->checkBefore($option['before'])) {
            return false;
        }

        if (isset($option['ext'])) {
            // 路由ext参数 优先于系统配置的URL伪静态后缀参数
            $url = preg_replace('/\.(' . $request->ext() . ')$/i', '', $url);
        }

        return $this->checkRule($request, $url, $depr, $completeMatch, $option);
    }

    /**
     * 检测路由规则
     * @access private
     * @param  Request   $request 请求对象
     * @param  string    $url URL地址
     * @param  string    $depr URL分隔符（全局）
     * @param  bool      $completeMatch   路由是否完全匹配
     * @param  array     $option   路由参数
     * @return array|false
     */
    private function checkRule($request, $url, $depr, $completeMatch = false, $option = [])
    {
        // 检查完整规则定义
        if (isset($this->pattern['__url__']) && !preg_match(0 === strpos($this->pattern['__url__'], '/') ? $this->pattern['__url__'] : '/^' . $this->pattern['__url__'] . '/', str_replace('|', $depr, $url))) {
            return false;
        }

        // 检查路由的参数分隔符
        if (isset($option['param_depr'])) {
            $url = str_replace(['|', $option['param_depr']], [$depr, '|'], $url);
        }

        $len1 = substr_count($url, '|');
        $len2 = substr_count($this->name, '/');

        // 多余参数是否合并
        $merge = !empty($option['merge_extra_vars']) ? true : false;

        if ($merge && $len1 > $len2) {
            $url = str_replace('|', $depr, $url);
            $url = implode('|', explode($depr, $url, $len2 + 1));
        }

        if (isset($option['complete_match'])) {
            $completeMatch = $option['complete_match'];
        }

        if ($len1 >= $len2 || strpos($this->name, '[')) {
            // 完整匹配
            if ($completeMatch && (!$merge && $len1 != $len2 && (false === strpos($this->name, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($this->name, '[')))) {
                return false;
            }

            $pattern = array_merge($this->parent->getPattern(), $this->pattern);

            if (false !== $match = $this->match($url, $pattern)) {
                // 匹配到路由规则
                return $this->parseRule($request, $this->name, $this->route, $url, $option, $match);
            }
        }

        return false;
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param  string    $url URL地址
     * @param  array     $pattern 变量规则
     * @return array|false
     */
    private function match($url, $pattern)
    {
        $m2 = explode('/', $this->name);
        $m1 = explode('|', $url);

        $var = [];

        foreach ($m2 as $key => $val) {
            // val中定义了多个变量 <id><name>
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                $value   = [];
                $replace = [];

                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name      = substr($name, 0, -1);
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')?';
                    } else {
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')';
                    }
                    $value[] = $name;
                }

                $val = str_replace($matches[0], $replace, $val);

                if (preg_match('/^' . $val . '$/', isset($m1[$key]) ? $m1[$key] : '', $match)) {
                    array_shift($match);
                    foreach ($value as $k => $name) {
                        if (isset($match[$k])) {
                            $var[$name] = $match[$k];
                        }
                    }
                    continue;
                } else {
                    return false;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $val      = substr($val, 1, -1);
                $optional = true;
            } else {
                $optional = false;
            }

            if (0 === strpos($val, ':')) {
                // URL变量
                $name = substr($val, 1);

                if (!$optional && !isset($m1[$key])) {
                    return false;
                }

                if (isset($m1[$key]) && isset($pattern[$name])) {
                    // 检查变量规则
                    if ($pattern[$name] instanceof \Closure) {
                        $result = call_user_func_array($pattern[$name], [$m1[$key]]);
                        if (false === $result) {
                            return false;
                        }
                    } elseif (!preg_match(0 === strpos($pattern[$name], '/') ? $pattern[$name] : '/^' . $pattern[$name] . '$/', $m1[$key])) {
                        return false;
                    }
                }

                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (!isset($m1[$key]) || 0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }

        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

}
