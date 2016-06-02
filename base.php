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

define('THINK_VERSION', '5.0.0 RC3');
define('START_TIME', microtime(true));
define('START_MEM', memory_get_usage());
define('EXT', '.php');
define('DS', DIRECTORY_SEPARATOR);
defined('THINK_PATH') or define('THINK_PATH', dirname(__FILE__) . DS);
define('LIB_PATH', THINK_PATH . 'library' . DS);
define('MODE_PATH', THINK_PATH . 'mode' . DS); // 系统应用模式目录
define('CORE_PATH', LIB_PATH . 'think' . DS);
define('TRAIT_PATH', LIB_PATH . 'traits' . DS);
defined('APP_PATH') or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(APP_PATH) . DS);
defined('EXTEND_PATH') or define('EXTEND_PATH', ROOT_PATH . 'extend' . DS);
defined('VENDOR_PATH') or define('VENDOR_PATH', ROOT_PATH . 'vendor' . DS);
defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DS);
defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . 'log' . DS);
defined('CACHE_PATH') or define('CACHE_PATH', RUNTIME_PATH . 'cache' . DS);
defined('TEMP_PATH') or define('TEMP_PATH', RUNTIME_PATH . 'temp' . DS);
defined('APP_NAMESPACE') or define('APP_NAMESPACE', 'app');
defined('CONF_PATH') or define('CONF_PATH', APP_PATH); // 配置文件目录
defined('CONF_EXT') or define('CONF_EXT', EXT); // 配置文件后缀
defined('APP_MULTI_MODULE') or define('APP_MULTI_MODULE', true); // 是否多模块
defined('ENV_PREFIX') or define('ENV_PREFIX', 'PHP_'); // 环境变量的配置前缀
defined('IS_API') or define('IS_API', false); // 是否API接口
defined('APP_AUTO_RUN') or define('APP_AUTO_RUN', true); // 是否自动运行
defined('APP_ROUTE_ON') or define('APP_ROUTE_ON', true); // 是否允许路由
defined('APP_ROUTE_MUST') or define('APP_ROUTE_MUST', true); // 是否严格检查路由
defined('CLASS_APPEND_SUFFIX') or define('CLASS_APPEND_SUFFIX', false); // 是否追加类名后缀
defined('APP_MODE') or define('APP_MODE', 'common'); // 应用模式 默认为普通模式

// 环境常量
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_WIN', strstr(PHP_OS, 'WIN') ? true : false);
