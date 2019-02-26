<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\route\dispatch;

use ReflectionMethod;
use think\App;
use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\Request;
use think\route\Dispatch;

class Controller extends Dispatch
{
    protected $controller;
    protected $actionName;

    public function init()
    {
        parent::init();

        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        // 是否自动转换控制器和操作名
        $convert = is_bool($this->convert) ? $this->convert : $this->rule->config('url_convert');
        // 获取控制器名
        $controller = strip_tags($result[0] ?: $this->rule->config('default_controller'));

        $this->controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $this->actionName = strip_tags($result[1] ?: $this->rule->config('default_action'));

        // 设置当前请求的控制器、操作
        $this->request
            ->setController(App::parseName($this->controller, 1))
            ->setAction($this->actionName);

        return $this;
    }

    public function exec()
    {
        try {
            // 实例化控制器
            $instance = $this->app->controller($this->controller);
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        $this->app['middleware']->controller(function (Request $request, $next) use ($instance) {
            // 获取当前操作名
            $action = $this->actionName . $this->rule->config('action_suffix');

            if (is_callable([$instance, $action])) {
                // 严格获取当前操作方法名
                $reflect    = new ReflectionMethod($instance, $action);
                $actionName = $reflect->getName();
                $this->request->setAction($actionName);

                // 自动获取请求变量
                $vars = array_merge($this->request->param(), $this->param);
            } else {
                // 操作不存在
                throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
            }

            $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);

            return $this->autoResponse($data);
        });

        return $this->app['middleware']->dispatch($this->request, 'controller');
    }
}
