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
use think\Container;
use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\Loader;
use think\route\Dispatch;

class Module extends Dispatch
{
    protected $controller;
    protected $actionName;

    public function __construct($dispatch, $param = [], $convert = null)
    {
        $this->app      = Container::get('app');
        $this->dispatch = $dispatch;
        $this->param    = $param;
        $this->convert  = $convert;
        $this->init();
    }

    protected function init()
    {
        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        if ($this->app->config('app.app_multi_module')) {
            // 多模块部署
            $module    = strip_tags(strtolower($result[0] ?: $this->app->config('app.default_module')));
            $bind      = $this->app['route']->getBind();
            $available = false;

            if ($bind && preg_match('/^[a-z]/is', $bind)) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if (empty($result[0])) {
                    $module = $bindModule;
                }
                $available = true;
            } elseif (!in_array($module, $this->app->config('app.deny_module_list')) && is_dir($this->app->getAppPath() . $module)) {
                $available = true;
            } elseif ($this->app->config('app.empty_module')) {
                $module    = $this->app->config('app.empty_module');
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $this->app['request']->module($module);
                $this->app->init($module);

                // 加载当前模块语言包
                $this->app['lang']->load($this->app->getAppPath() . $module . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $this->app['request']->langset() . '.php');

                // 模块请求缓存检查
                $this->app['request']->cache(
                    $this->app->config('app.request_cache'),
                    $this->app->config('app.request_cache_expire'),
                    $this->app->config('app.request_cache_except')
                );
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        } else {
            // 单一模块部署
            $module = '';
            $this->app['request']->module($module);
        }

        // 当前模块路径
        $this->app->setModulePath($this->app->getAppPath() . ($module ? $module . DIRECTORY_SEPARATOR : ''));

        // 是否自动转换控制器和操作名
        $convert = is_bool($this->convert) ? $this->convert : $this->app->config('app.url_convert');
        // 获取控制器名
        $controller       = strip_tags($result[1] ?: $this->app->config('app.default_controller'));
        $this->controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $this->actionName = strip_tags($result[2] ?: $this->app->config('app.default_action'));

        // 设置当前请求的控制器、操作
        $this->app['request']->controller(Loader::parseName($this->controller, 1))->action($this->actionName);

    }

    public function run()
    {
        // 监听module_init
        $this->app['hook']->listen('module_init');

        // 实例化控制器
        try {
            $instance = $this->app->controller($this->controller,
                $this->app->config('app.url_controller_layer'),
                $this->app->config('app.controller_suffix'),
                $this->app->config('app.empty_controller'));
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 获取当前操作名
        $action = $this->actionName . $this->app->config('app.action_suffix');

        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];

            // 严格获取当前操作方法名
            $reflect    = new ReflectionMethod($instance, $action);
            $methodName = $reflect->getName();
            $suffix     = $this->app->config('app.action_suffix');
            $actionName = $suffix ? substr($methodName, 0, -strlen($suffix)) : $methodName;
            $this->app['request']->action($actionName);

            // 自动获取请求变量
            $vars = $this->app->config('app.url_param_type')
            ? $this->app['request']->route()
            : $this->app['request']->param();
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
        return Container::getInstance()->invokeReflectMethod($instance, $reflect, $vars);
    }
}
