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

define('EXT', '.php');
define('DS', DIRECTORY_SEPARATOR);
defined('THINK_PATH') or define('THINK_PATH', __DIR__ . DS);
define('LIB_PATH', THINK_PATH . 'library' . DS);
define('CORE_PATH', LIB_PATH . 'think' . DS);
define('TRAIT_PATH', LIB_PATH . 'traits' . DS);
defined('APP_PATH') or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(realpath(APP_PATH)) . DS);
defined('EXTEND_PATH') or define('EXTEND_PATH', ROOT_PATH . 'extend' . DS);
defined('VENDOR_PATH') or define('VENDOR_PATH', ROOT_PATH . 'vendor' . DS);
defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DS);
defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . 'log' . DS);
defined('CACHE_PATH') or define('CACHE_PATH', RUNTIME_PATH . 'cache' . DS);
defined('TEMP_PATH') or define('TEMP_PATH', RUNTIME_PATH . 'temp' . DS);
defined('CONF_PATH') or define('CONF_PATH', APP_PATH); // 配置文件目录
defined('CONF_EXT') or define('CONF_EXT', EXT); // 配置文件后缀
defined('ENV_PREFIX') or define('ENV_PREFIX', 'PHP_'); // 环境变量的配置前缀

// 环境常量
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);

// 载入Loader类
require __DIR__ . '/library/think/Loader.php';

// 加载环境变量配置文件
if (is_file(ROOT_PATH . '.env')) {
    $env = parse_ini_file(ROOT_PATH . '.env', true);
    foreach ($env as $key => $val) {
        $name = ENV_PREFIX . strtoupper($key);
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $item = $name . '_' . strtoupper($k);
                putenv("$item=$v");
            }
        } else {
            putenv("$name=$val");
        }
    }
}

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
