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

class RuleItem extends Rule
{
    // 路由规则
    protected $rule;
    // 路由标识
    protected $name;
    // 路由地址
    protected $route;
    // 请求类型
    protected $method;
    protected $group;

    // 路由匹配模式
    protected $completeMatch = false;

    /**
     * 架构函数
     * @access public
     * @param Route             $router 路由实例
     * @param RuleGroup         $group 路由所属分组对象
     * @param string|array      $rule 路由规则
     * @param string|\Closure   $route 路由地址
     * @param string            $method 请求类型
     * @param array             $option 路由参数
     * @param array             $pattern 变量规则
     */
    public function __construct(Route $router, RuleGroup $group, $rule, $route, $method = '*', $option = [], $pattern = [])
    {
        $this->router = $router;
        $this->group  = $group;

        $this->setRule($rule, $option);

        $this->setMethod($method);

        $this->route   = $route;
        $this->option  = array_merge($group->getOption(), $option);
        $this->pattern = $pattern;
    }

    public function setRule($rule, $option = [])
    {
        if ('$' == substr($rule, -1, 1)) {
            // 是否完整匹配
            $rule = substr($rule, 0, -1);

            $this->completeMatch = true;
        } elseif (!empty($option['complete_match'])) {
            $this->completeMatch = true;
        }

        $this->rule = trim($rule, '/');
    }

    public function getRule()
    {
        return $this->rule;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function isMiss()
    {
        return '__miss__' == $this->rule;
    }

    public function isAuto()
    {
        return '__auto__' == $this->rule;
    }

    public function setMethod($method, &$option = [])
    {
        $method = strtolower($method);

        if (strpos($method, '|')) {
            $option['method'] = $method;
            $method           = '*';
        }

        $this->method = $method;
    }

    // 检测路由规则
    public function check($request, $url, $depr = '/')
    {
        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        if (isset($this->option['ext'])) {
            // 路由ext参数 优先于系统配置的URL伪静态后缀参数
            $url = preg_replace('/\.' . $request->ext() . '$/i', '', $url);
        }

        return $this->checkRule($request, $url, $depr);
    }

    /**
     * 检测路由规则
     * @access private
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @param array     $option 路由参数
     * @param string    $depr URL分隔符（全局）
     * @return array|false
     */
    private function checkRule($request, $url, $depr)
    {
        // 检查完整规则定义
        if (isset($this->pattern['__url__']) && !preg_match('/^' . $this->pattern['__url__'] . '/', str_replace('|', $depr, $url))) {
            return false;
        }

        // 检查路由的参数分隔符
        if (isset($this->option['param_depr'])) {
            $url = str_replace(['|', $this->option['param_depr']], [$depr, '|'], $url);
        }

        $len1 = substr_count($url, '|');
        $len2 = substr_count($this->rule, '/');

        // 多余参数是否合并
        $merge = !empty($this->option['merge_extra_vars']) ? true : false;

        if ($merge && $len1 > $len2) {
            $url = str_replace('|', $depr, $url);
            $url = implode('|', explode($depr, $url, $len2 + 1));
        }

        if ($len1 >= $len2 || strpos($this->rule, '[')) {
            if (!empty($this->completeMatch)) {
                // 完整匹配
                if (!$merge && $len1 != $len2 && (false === strpos($this->rule, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($this->rule, '['))) {
                    return false;
                }
            }

            $pattern = array_merge($this->group->getPattern(), $this->pattern);

            if (false !== $match = $this->match($url, $pattern)) {
                // 匹配到路由规则
                return $this->parseRule($request, $this->rule, $this->route, $url, $this->option, $match);
            }
        }

        return false;
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @return array|false
     */
    private function match($url, $pattern)
    {
        $m2 = explode('/', $this->rule);
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
                    } elseif (!preg_match('/^' . $pattern[$name] . '$/', $m1[$key])) {
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
