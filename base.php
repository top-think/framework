<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 载入Loader类
require __DIR__ . '/library/think/Loader.php';

// 注册自动加载
think\Loader::register();

// 注册错误和异常处理机制
think\Error::register();

// 注册核心类到容器
think\Container::getInstance()->bind([
    'app'     => 'think\App',
    'cache'   => 'think\Cache',
    'config'  => 'think\Config',
    'cookie'  => 'think\Cookie',
    'debug'   => 'think\Debug',
    'hook'    => 'think\Hook',
    'lang'    => 'think\Lang',
    'log'     => 'think\Log',
    'request' => 'think\Request',
    'reponse' => 'think\Reponse',
    'route'   => 'think\Route',
    'session' => 'think\Session',
    'url'     => 'think\Url',
]);

// 注册核心类的静态代理
think\Facade::bind([
    'think\facade\App'     => 'think\App',
    'think\facade\Cache'   => 'think\Cache',
    'think\facade\Config'  => 'think\Config',
    'think\facade\Cookie'  => 'think\Cookie',
    'think\facade\Debug'   => 'think\Debug',
    'think\facade\Hook'    => 'think\Hook',
    'think\facade\Lang'    => 'think\Lang',
    'think\facade\Loader'  => 'think\Loader',
    'think\facade\Log'     => 'think\Log',
    'think\facade\Request' => 'think\Request',
    'think\facade\Reponse' => 'think\Reponse',
    'think\facade\Route'   => 'think\Route',
    'think\facade\Session' => 'think\Session',
    'think\facade\Url'     => 'think\Url',
]);

// 加载惯例配置文件
think\facade\Config::set(include __DIR__ . '/convention.php');
