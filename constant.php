<?php
// +----------------------------------------------------------------------
// | shuguo constant
// +----------------------------------------------------------------------
// | Copyright (c) 2018~2050 上海数果科技有限公司 All rights reserved.
// +----------------------------------------------------------------------
// +----------------------------------------------------------------------
// | Website: http://chinashuguo.com
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: opensmarty <opensmarty@163.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 定义系统常量
// +----------------------------------------------------------------------

//设置全局变量sg
$sg['_debug'] = false;        //调试模式

$sg['_define'] = array();    //全局常量
$sg['_config'] = array();    //全局配置
$sg['_access'] = array();    //访问配置
$sg['_router'] = array();    //路由配置

//自定义常量函数
if (!function_exists('sgdefine')) {
    /**
     * 定义常量,判断是否未定义.
     *
     * @param  string $name  常量名
     * @param  string $value 常量值
     * @return string $str 返回常量的值
     */
    function sgdefine($name, $value)
    {
        global $sg;
        //定义未定义的常量
        if (!defined($name)) {
            //定义新常量
            define($name, $value);
        } else {
            //返回已定义的值
            $value = constant($name);
        }
        //缓存已定义常量列表
        $sg['_define'][$name] = $value;

        return $value;
    }
}

//Think框架常量的基本配置
sgdefine('THINK_VERSION', '5.1.18');
sgdefine('THINK_START_TIME', microtime(true));
sgdefine('THINK_START_MEM', memory_get_usage());
sgdefine('EXT', '.php');
sgdefine('DS', DIRECTORY_SEPARATOR);

// 环境常量
sgdefine('IS_CLI', PHP_SAPI == 'cli' ? true : false);
sgdefine('IS_WIN', strpos(PHP_OS, 'WIN') !== false);
sgdefine('IS_HTTPS', 0);

if(!function_exists('getScriptName')){
    /**
     * 获取当前url
     * @return string
     */
    function getScriptName(){
        $_scriptName = '';

        if (IS_CLI) {
            $_temp = explode('.php', $_SERVER['PHP_SELF']);
            $_scriptName =  rtrim(str_replace($_SERVER['HTTP_HOST'], '', $_temp[0] . '.php'), '/');
        } else {
            $_scriptName =  rtrim($_SERVER['SCRIPT_NAME'], '/');
        }

        return $_scriptName;
    }
}

// 获取当前脚本
sgdefine('_PHP_FILE_', getScriptName());

//基本常量定义
sgdefine('SITE_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS);
sgdefine('APP_PATH', SITE_PATH . 'application' . DS);
sgdefine('ROOT_PATH', dirname(realpath(APP_PATH)) . DS);
sgdefine('ROOT_FILE', basename(_PHP_FILE_));

if(!function_exists('getHostName')){
    /**
     * 获取当前url
     * @return string
     */
    function getHostName(){
        $_host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
        $_host = strip_tags($_host);

        return $_host;
    }
}

if(!function_exists('getRootPath')){
    /**
     * 获取当前url
     * @return string
     */
    function getRootPath(){
        $_root = dirname(_PHP_FILE_);
        if($_root == '/' || $_root == '\\'){
            $_root = '';
        } else {
            $_root = rtrim($_root, '/');
        }

        return $_root;
    }
}

sgdefine('__ROOT__', getRootPath());
sgdefine('SITE_DOMAIN', getHostName());
sgdefine('SITE_URL', (IS_HTTPS ? 'https:' : 'http:') . '//' . SITE_DOMAIN . __ROOT__);

sgdefine('APP_URL', SITE_URL . '/application');    # 应用内部图标 等元素

sgdefine('THINK_PATH', SITE_PATH . 'thinkphp' . DS);
sgdefine('LIB_PATH', THINK_PATH . 'library' . DS);
sgdefine('CORE_PATH', LIB_PATH . 'think' . DS);
sgdefine('TRAIT_PATH', LIB_PATH . 'traits' . DS);

sgdefine('ROUTE_PATH', SITE_PATH . 'route' . DS);
sgdefine('EXTEND_PATH',  SITE_PATH . 'extend' . DS);
sgdefine('VENDOR_PATH', SITE_PATH . 'vendor' . DS);
sgdefine('RUNTIME_PATH', SITE_PATH . 'runtime' . DS);
sgdefine('LOG_PATH', RUNTIME_PATH . 'log' . DS);
sgdefine('CACHE_PATH', RUNTIME_PATH . 'cache' . DS);
sgdefine('SESSION_PATH', RUNTIME_PATH . 'session' . DS);
sgdefine('TEMP_PATH', RUNTIME_PATH . 'temp' . DS);
sgdefine('CONF_PATH', SITE_PATH . 'config' . DS); // 配置文件目录
sgdefine('CONF_EXT', EXT); // 配置文件后缀
sgdefine('ENV_PREFIX', 'PHP_'); // 环境变量的配置前缀

sgdefine('DATA_PATH', SITE_PATH . 'data' . DS);
sgdefine('DATA_URL', SITE_URL . '/data');

sgdefine('UPLOAD_PATH', DATA_PATH . 'uploads' . DS);
sgdefine('UPLOAD_URL', DATA_URL . '/uploads');

sgdefine('THEME_PATH', SITE_PATH . 'theme' . DS);
sgdefine('THEME_URL', SITE_URL . 'theme' . DS);
sgdefine('THEME_PUBLIC', THEME_PATH . 'static');
sgdefine('THEME_PUBLIC_URL', THEME_URL . '/static');

sgdefine('NOW_TIME', $_SERVER ['REQUEST_TIME']);
sgdefine('REQUEST_METHOD', $_SERVER ['REQUEST_METHOD']);
sgdefine('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
sgdefine('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
sgdefine('TOKEN', 'sg_wx');