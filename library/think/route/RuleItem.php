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
    /**
     * 路由规则
     * @var string
     */
    protected $rule;

    /**
     * 路由地址
     * @var string|\Closure
     */
    protected $route;

    /**
     * 请求类型
     * @var string
     */
    protected $method;

    /**
     * 架构函数
     * @access public
     * @param  Route             $router 路由实例
     * @param  RuleGroup         $group 路由所属分组对象
     * @param  string            $name 路由标识
     * @param  string|array      $rule 路由规则
     * @param  string            $method 请求类型
     * @param  string|\Closure   $route 路由地址
     * @param  array             $option 路由参数
     * @param  array             $pattern 变量规则
     */
    public function __construct(Route $router, RuleGroup $group, $name, $rule, $route, $method = '*', $option = [], $pattern = [])
    {
        $this->router  = $router;
        $this->parent  = $group;
        $this->name    = $name;
        $this->route   = $route;
        $this->method  = $method;
        $this->option  = $option;
        $this->pattern = $pattern;

        $this->setRule($rule);
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

        $rule = '/' != $rule ? ltrim($rule, '/') : '/';

        if ($this->parent && $prefix = $this->parent->getFullName()) {
            $rule = $prefix . ($rule ? '/' . ltrim($rule, '/') : '');
        }

        $this->rule = $rule;

        // 生成路由标识的快捷访问
        $this->setRuleName();
    }

    /**
     * 检查后缀
     * @access public
     * @param  string     $ext
     * @return $this
     */
    public function ext($ext = '')
    {
        $this->option('ext', $ext);
        $this->setRuleName(true);

        return $this;
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
     * 设置为自动路由
     * @access public
     * @return $this
     */
    public function isAuto()
    {
        $this->parent->setAutoRule($this);
        return $this;
    }

    /**
     * 设置为MISS路由
     * @access public
     * @return $this
     */
    public function isMiss()
    {
        $this->parent->setMissRule($this);
        return $this;
    }

    /**
     * 设置路由标识 用于URL反解生成
     * @access protected
     * @param  bool     $first   是否插入开头
     * @return void
     */
    protected function setRuleName($first = false)
    {
        if ($this->name) {
            $this->router->setRuleName($this->rule, $this->name, $this->option, $first);
        }
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
        if ($dispatch = $this->checkCrossDomain($request)) {
            // 允许跨域
            return $dispatch;
        }

        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        // 合并分组参数
        $option = $this->mergeGroupOptions();

        if (!empty($option['append'])) {
            $request->route($option['append']);
        }

        // 是否区分 / 地址访问
        if (!empty($option['remove_slash']) && '/' != $this->rule) {
            $this->rule = rtrim($this->rule, '/');
            $url        = rtrim($url, '|');
        }

        // 检查前置行为
        if (isset($option['before']) && false === $this->checkBefore($option['before'])) {
            return false;
        }

        if (isset($option['ext'])) {
            // 路由ext参数 优先于系统配置的URL伪静态后缀参数
            $url = preg_replace('/\.(' . $request->ext() . ')$/i', '', $url);
        }

        if (isset($option['complete_match'])) {
            $completeMatch = $option['complete_match'];
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

        $pattern = array_merge($this->parent->getPattern(), $this->pattern);

        if (false !== $match = $this->match($url, $pattern, $depr, $completeMatch)) {
            // 匹配到路由规则
            return $this->parseRule($request, $this->rule, $this->route, $url, $option, $match);
        }

        return false;
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param  string    $url URL地址
     * @param  array     $pattern 变量规则
     * @param  string    $depr URL分隔符（全局）
     * @param  bool      $completeMatch   路由是否完全匹配
     * @return array|false
     */
    private function match($url, $pattern, $depr, $completeMatch)
    {
        $var  = [];
        $url  = str_replace('|', $depr, $url);
        $rule = str_replace('/', $depr, $this->rule);

        if (preg_match_all('/(?:[\/\-]<\w+\??>|[\/\-]\[?\:\w+\]?)/', $rule, $matches)) {
            foreach ($matches[0] as $name) {
                $optional = '';
                $slash    = substr($name, 0, 1);

                if (strpos($name, ']')) {
                    $name     = substr($name, 3, -1);
                    $optional = '?';
                } elseif (strpos($name, '?')) {
                    $name     = substr($name, 2, -2);
                    $optional = '?';
                } elseif (strpos($name, '>')) {
                    $name = substr($name, 2, -1);
                } else {
                    $name = substr($name, 2);
                }

                $replace[] = '([$\\' . $slash . '](?<' . $name . '>' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . '))' . $optional;
            }

            $regex = str_replace($matches[0], $replace, $rule);
            $regex = str_replace([')/', ')-'], [')\/', ')\-'], $regex);

            if (!preg_match('/^' . $regex . ($completeMatch ? '$' : '') . '/', $url, $match)) {
                return false;
            }

            array_shift($match);
            foreach ($match as $key => $val) {
                if (is_string($key)) {
                    $var[$key] = $val;
                }
            }
        } elseif (0 !== strcasecmp($this->rule, $url)) {
            return false;
        }

        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

}
