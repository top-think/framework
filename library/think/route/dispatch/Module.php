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

namespace think\route\dispatch;

use ReflectionMethod;
use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\Loader;
use think\route\Dispatch;

class Module extends Dispatch
{
    protected $controller;
    protected $actionName;

    protected function init()
    {
        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        if ($this->router->getConfig('app_multi_module')) {
            // 多模块部署
            $module    = strip_tags(strtolower($result[0] ?: $this->router->getConfig('default_module')));
            $bind      = $this->router->getRouter()->getBind();
            $available = false;

            if ($bind && preg_match('/^[a-z]/is', $bind)) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if (empty($result[0])) {
                    $module = $bindModule;
                }
                $available = true;
            } elseif (!in_array($module, $this->router->getConfig('deny_module_list')) && is_dir($this->app->getAppPath() . $module)) {
                $available = true;
            } elseif ($this->router->getConfig('empty_module')) {
                $module    = $this->router->getConfig('empty_module');
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $this->request->module($module);
                $this->app->init($module);
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        }

        // 是否自动转换控制器和操作名
        $convert = is_bool($this->convert) ? $this->convert : $this->router->getConfig('url_convert');
        // 获取控制器名
        $controller       = strip_tags($result[1] ?: $this->router->getConfig('default_controller'));
        $this->controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $this->actionName = strip_tags($result[2] ?: $this->router->getConfig('default_action'));

        // 设置当前请求的控制器、操作
        $this->request->controller(Loader::parseName($this->controller, 1))->action($this->actionName);

    }

    public function exec()
    {
        // 监听module_init
        $this->app['hook']->listen('module_init');

        // 实例化控制器
        try {
            $instance = $this->app->controller($this->controller,
                $this->router->getConfig('url_controller_layer'),
                $this->router->getConfig('controller_suffix'),
                $this->router->getConfig('empty_controller'));
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 获取当前操作名
        $action = $this->actionName . $this->router->getConfig('action_suffix');

        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];

            // 严格获取当前操作方法名
            $reflect    = new ReflectionMethod($instance, $action);
            $methodName = $reflect->getName();
            $suffix     = $this->router->getConfig('action_suffix');
            $actionName = $suffix ? substr($methodName, 0, -strlen($suffix)) : $methodName;
            $this->request->action($actionName);

            // 自动获取请求变量
            $vars = $this->router->getConfig('url_param_type')
            ? $this->request->route()
            : $this->request->param();
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call    = [$instance, '_empty'];
            $vars    = [$this->actionName];
            $reflect = new ReflectionMethod($instance, '_empty');
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        $this->app['hook']->listen('action_begin', $call);

        return $this->app->invokeReflectMethod($instance, $reflect, $vars);
    }
}
