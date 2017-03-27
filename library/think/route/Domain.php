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

class Domain extends Rule
{
    // 域名
    protected $name;
    // 路由规则
    protected $rules = [];

    /**
     * 架构函数
     * @access public
     * @param string      $rule     域名
     * @param array       $option   域名路由参数
     * @param array       $pattern  域名变量规则
     */
    public function __construct(Route $router, $name = '', $option = [], $pattern = [])
    {
        $this->router  = $router;
        $this->name    = $name;
        $this->option  = $option;
        $this->pattern = $pattern;
    }

    // 检测域名下的路由
    public function check($request, $url, $depr = '/')
    {
        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }
    }

    public function addRule($rule, $method = '*')
    {
        $this->rules[$method][] = $rule;

        return $this;
    }

}
