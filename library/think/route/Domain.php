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

use think\Container;
use think\Loader;
use think\Response;
use think\Route;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;
use think\route\dispatch\Module as ModuleDispatch;
use think\route\dispatch\Response as ResponseDispatch;

class Domain extends RuleGroup
{
    /**
     * 架构函数
     * @access public
     * @param  Route       $router   路由对象
     * @param  string      $name     分组名称
     * @param  mixed       $rule     域名路由
     * @param  array       $option   路由参数
     * @param  array       $pattern  变量规则
     */
    public function __construct(Route $router, $name = '', $rule = null, $option = [], $pattern = [])
    {
        $this->router  = $router;
        $this->name    = trim($name, '/');
        $this->option  = $option;
        $this->rule    = $rule;
        $this->pattern = $pattern;
    }

    /**
     * 检测域名路由
     * @access public
     * @param  Request      $request  请求对象
     * @param  string       $url      访问地址
     * @param  string       $depr     路径分隔符
     * @param  bool         $completeMatch   路由是否完全匹配
     * @return Dispatch|false
     */
    public function check($request, $url, $depr = '/', $completeMatch = false)
    {
        if ($this->rule) {
            // 延迟解析域名路由
            if ($this->rule instanceof Response) {
                return new ResponseDispatch($this->rule);
            }

            $group = new RuleGroup($this->router);

            $this->addRule($group);

            $this->router->setGroup($group);

            $this->router->parseGroupRule($this, $this->rule);

            $this->rule = null;
        }

        // 检测别名路由
        if ($this->router->getAlias($url) || $this->router->getAlias(strstr($url, '|', true))) {
            // 检测路由别名
            $result = $this->checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }

        // 检测URL绑定
        $result = $this->checkUrlBind($url, $depr);

        if (false !== $result) {
            return $result;
        }

        return parent::check($request, $url, $depr, $completeMatch);
    }

    /**
     * 检测路由别名
     * @access private
     * @param  Request   $request
     * @param  string    $url URL地址
     * @param  string    $depr URL分隔符
     * @return Dispatch|false
     */
    private function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('|', $url);
        $alias = array_shift($array);
        $item  = $this->router->getAlias($alias);

        if (is_array($item)) {
            list($rule, $option) = $item;
            $action              = $array[0];

            if (isset($option['allow']) && !in_array($action, explode(',', $option['allow']))) {
                // 允许操作
                return false;
            } elseif (isset($option['except']) && in_array($action, explode(',', $option['except']))) {
                // 排除操作
                return false;
            }

            if (isset($option['method'][$action])) {
                $option['method'] = $option['method'][$action];
            }
        } else {
            $rule = $item;
        }

        $bind = implode('|', $array);

        // 参数有效性检查
        if (isset($option) && !$this->checkOption($option, $request)) {
            // 路由不匹配
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // 路由到类
            return $this->bindToClass($bind, substr($rule, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // 路由到控制器类
            return $this->bindToController($bind, substr($rule, 1), $depr);
        } else {
            // 路由到模块/控制器
            return $this->bindToModule($bind, $rule, $depr);
        }
    }

    /**
     * 检测URL绑定
     * @access private
     * @param  string    $url URL地址
     * @param  string    $depr URL分隔符
     * @return Dispatch|false
     */
    private function checkUrlBind(&$url, $depr = '/')
    {
        $bind = $this->router->getBind($this->name);

        if (!empty($bind)) {
            // 记录绑定信息
            Container::get('app')->log('[ BIND ] ' . var_export($bind, true));

            // 如果有URL绑定 则进行绑定检测
            if (0 === strpos($bind, '\\')) {
                // 绑定到类
                return $this->bindToClass($url, substr($bind, 1), $depr);
            } elseif (0 === strpos($bind, '@')) {
                // 绑定到控制器类
                return $this->bindToController($url, substr($bind, 1), $depr);
            } elseif (0 === strpos($bind, ':')) {
                // 绑定到命名空间
                return $this->bindToNamespace($url, substr($bind, 1), $depr);
            }
        }

        return false;
    }

    /**
     * 绑定到类
     * @access public
     * @param  string    $url URL地址
     * @param  string    $class 类名（带命名空间）
     * @param  string    $depr URL分隔符
     * @return CallbackDispatch
     */
    public function bindToClass($url, $class, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Container::get('config')->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new CallbackDispatch([$class, $action]);
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param  string    $url URL地址
     * @param  string    $namespace 命名空间
     * @param  string    $depr URL分隔符
     * @return CallbackDispatch
     */
    public function bindToNamespace($url, $namespace, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : Container::get('config')->get('default_controller');
        $method = !empty($array[1]) ? $array[1] : Container::get('config')->get('default_action');

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2]);
        }

        return new CallbackDispatch([$namespace . '\\' . Loader::parseName($class, 1), $method]);
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param  string    $url URL地址
     * @param  string    $controller 控制器名 （支持带模块名 index/user ）
     * @param  string    $depr URL分隔符
     * @return ControllerDispatch
     */
    public function bindToController($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Container::get('config')->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ControllerDispatch($controller . '/' . $action);
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param  string    $url URL地址
     * @param  string    $controller 控制器类名（带命名空间）
     * @param  string    $depr URL分隔符
     * @return ModuleDispatch
     */
    public function bindToModule($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Container::get('config')->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ModuleDispatch($controller . '/' . $action);
    }

}
