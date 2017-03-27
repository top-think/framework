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

abstract class Rule
{
    // 路由对象实例
    protected $router;
    // 路由参数
    protected $option = [];
    // 路由变量规则
    protected $pattern = [];

    // 路由匹配模式
    private $completeMatch = false;
    // 不同请求类型的方法前缀
    private $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    abstract public function check($request, $url, $depr = '/');

    public function option(array $option = [])
    {
        $this->option = $option;

        return $this;
    }

    public function pattern(array $pattern = [])
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPattern($name = '')
    {
        if ('' === $name) {
            return $this->pattern;
        }
        return isset($this->pattern[$name]) ? $this->pattern[$name] : null;
    }

    public function getOption($name = '')
    {
        if ('' === $name) {
            return $this->option;
        }

        return isset($this->option[$name]) ? $this->option[$name] : null;
    }

    /**
     * 路由参数有效性检查
     * @access protected
     * @param array     $option 路由参数
     * @param Request   $request Request对象
     * @return bool
     */
    protected function checkOption($option, Request $request)
    {
        if ((isset($option['method']) && is_string($option['method']) && false === stripos($option['method'], $request->method()))
            || (isset($option['ajax']) && $option['ajax'] && !$request->isAjax()) // Ajax检测
             || (isset($option['ajax']) && !$option['ajax'] && $request->isAjax()) // 非Ajax检测
             || (isset($option['pjax']) && $option['pjax'] && !$request->isPjax()) // Pjax检测
             || (isset($option['pjax']) && !$option['pjax'] && $request->isPjax()) // 非Pjax检测
             || (isset($option['ext']) && false === stripos('|' . $option['ext'] . '|', '|' . $request->ext() . '|')) // 伪静态后缀检测
             || (isset($option['deny_ext']) && false !== stripos('|' . $option['deny_ext'] . '|', '|' . $request->ext() . '|'))
            || (isset($option['domain']) && !in_array($option['domain'], [$_SERVER['HTTP_HOST'], $this->subDomain])) // 域名检测
             || (isset($option['https']) && $option['https'] && !$request->isSsl()) // https检测
             || (isset($option['https']) && !$option['https'] && $request->isSsl()) // https检测
             || (!empty($option['before_behavior']) && false === Hook::exec($option['before_behavior'])) // 行为检测
             || (!empty($option['callback']) && is_callable($option['callback']) && false === call_user_func($option['callback'])) // 自定义检测
        ) {
            return false;
        }

        return true;
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access protected
     * @param string    $rule 路由规则
     * @param array     $var 变量
     * @return void
     */
    protected function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if ($this->app['config']->get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }

        // 设置当前请求的参数
        $this->app['request']->route($var);
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access protected
     * @param string    $url URL地址
     * @return array
     */
    protected function parseUrlPath($url)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');
        $var = [];

        if (false !== strpos($url, '?')) {
            // [模块/控制器/操作?]参数1=值1&参数2=值2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [模块/控制器/操作]
            $path = explode('/', $url);
        } elseif (false !== strpos($url, '=')) {
            // 参数1=值1&参数2=值2...
            parse_str($url, $var);
        } else {
            $path = [$url];
        }

        return [$path, $var];
    }

}
