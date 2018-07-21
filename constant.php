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


//记录开始运行时间
$GLOBALS['_beginTime'] = microtime(true);

// 记录内存初始使用
sgdefine('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));

// 应用开发中的配置
// sgdefine('DEBUG', false);

// 设置全局变量sg
$sg['_debug'] = false;        // 调试模式

$sg['_define'] = array();    // 全局常量
$sg['_config'] = array();    // 全局配置
$sg['_access'] = array();    // 访问配置
$sg['_router'] = array();    // 路由配置

//Think框架常量的基本配置
sgdefine('THINK_VERSION', '5.1.18');
sgdefine('THINK_START_TIME', microtime(true));
sgdefine('THINK_START_MEM', memory_get_usage());
sgdefine('EXT', '.php');
sgdefine('DS', transfer_slash(DIRECTORY_SEPARATOR));

// 环境常量
sgdefine('IS_CLI', PHP_SAPI == 'cli' ? true : false);
sgdefine('IS_WIN', strpos(PHP_OS, 'WIN') !== false);
sgdefine('IS_HTTPS', 0);
sgdefine('API_VERSION', 'v1');
sgdefine('_PHP_FILE_', getScriptName());
sgdefine('__ROOT__', getRootPath());

//基本常量定义
sgdefine('GROUP_NAME', 'shuguo');
sgdefine('GROUP_CORE_MODE', 'core');

sgdefine('SITE_NAME', 'sgs-api');
sgdefine('SITE_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS);

// 新系统需要的一些配置
sgdefine('SG_ROOT', SITE_PATH);        // SG根
sgdefine('SG_APPLICATION', SG_ROOT . 'application'); // 应用存在的目录
sgdefine('SG_CONFIGURE', SG_ROOT . 'config'); // 配置文件存在的目录
sgdefine('SG_STORAGE', SG_ROOT . 'storage');            // 储存目录，需要可以公开访问，相对于域名根

sgdefine('ROOT_PATH', SITE_PATH);
sgdefine('ROOT_FILE', basename(_PHP_FILE_));

sgdefine('SITE_DOMAIN', getSiteHost());
sgdefine('SITE_URL', (IS_HTTPS ? 'https:' : 'http:') . '//' . SITE_DOMAIN . __ROOT__);

sgdefine('THINK_PATH', SITE_PATH . 'thinkphp' . DS);
sgdefine('LIB_PATH', THINK_PATH . 'library' . DS);
sgdefine('CORE_PATH', LIB_PATH . 'think' . DS);
sgdefine('TRAIT_PATH', LIB_PATH . 'traits' . DS);
sgdefine('ROUTE_PATH', SITE_PATH . 'route' . DS);
sgdefine('EXTEND_PATH', SITE_PATH . 'extend' . DS);
sgdefine('RUNTIME_PATH', SITE_PATH . 'runtime' . DS);
sgdefine('LOG_PATH', RUNTIME_PATH . 'log' . DS);
sgdefine('CACHE_PATH', RUNTIME_PATH . 'cache' . DS);
sgdefine('SESSION_PATH', RUNTIME_PATH . 'session' . DS);
sgdefine('TEMP_PATH', RUNTIME_PATH . 'temp' . DS);
sgdefine('CONF_PATH', SITE_PATH . 'config' . DS); // 配置文件目录
sgdefine('VENDOR_PATH', SITE_PATH . 'vendor' . DS);
sgdefine('GROUP_PATH', VENDOR_PATH . GROUP_NAME . DS . 'src' . DS);
sgdefine('GROUP_CORE_PATH', GROUP_PATH . GROUP_CORE_MODE . DS . 'src' . DS);

sgdefine('CONF_EXT', EXT); // 配置文件后缀
sgdefine('ENV_PREFIX', 'PHP_'); // 环境变量的配置前缀

sgdefine('APPS_PATH', SITE_PATH . 'application' . DS);
sgdefine('APPS_URL', SITE_URL . DS . 'application');    # 应用内部图标 等元素

sgdefine('ADDON_PATH',    SITE_PATH . 'addons' . DS);
sgdefine('ADDON_URL',    SITE_URL . DS .'addons');

sgdefine('DATA_PATH', SITE_PATH . 'data' . DS);
sgdefine('DATA_URL', SITE_URL . DS . 'data');

sgdefine('UPLOAD_PATH', DATA_PATH . 'uploads' . DS);
sgdefine('UPLOAD_URL', DATA_URL . DS . 'uploads');

// 公共资源目录常量
sgdefine('PUBLIC_PATH', SITE_PATH . 'public' . DS);
sgdefine('PUBLIC_URL', SITE_URL . DS . 'public');

// 前端资源目录常量
sgdefine('STATIC_PATH', PUBLIC_PATH . 'static' . DS);
sgdefine('STATIC_URL', PUBLIC_URL . DS . 'static');

sgdefine('NOW_TIME', $_SERVER ['REQUEST_TIME']);
sgdefine('REQUEST_METHOD', $_SERVER ['REQUEST_METHOD']);
sgdefine('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
sgdefine('IS_POST', REQUEST_METHOD == 'POST' ? true : false);

// 自定义核心方法
/**
 * 判断是否为调试模式
 * @return bool
 */
function isDebug() {
    // TODO
}

/**
 * @titile定义常量,判断是否未定义.
 * @param  string $name  常量名
 * @param  string $value 常量值
 * @return string $str 返回常量的值
 */
function sgdefine($name, $value)
{
    global $sg;
    // 定义未定义的常量
    if (!defined($name)) {
        // 定义新常量
        define($name, $value);
    } else {
        // 返回已定义的值
        $value = constant($name);
    }
    // 缓存已定义常量列表
    $sg['_define'][$name] = $value;
    
    return $value;
}

/**
 * @title 转义反斜杠为正斜杠
 * @param $slash
 * @return mixed
 */
function transfer_slash($slash)
{
    return str_replace('\\', '/', $slash);
}

/**
 * 获取当前脚本
 * @return string
 */
function getScriptName()
{
    $scriptName = '';
    if (IS_CLI) {
        $scriptName = explode(EXT, $_SERVER['PHP_SELF']);
        $scriptName = rtrim(str_replace($_SERVER['HTTP_HOST'], '', $scriptName[0] . '.php'), '/');
    } else {
        $scriptName = rtrim($_SERVER['SCRIPT_NAME'], '/');
    }
    
    return $scriptName;
}

/**
 * 获取当前主机
 * @return string
 */
function getSiteHost()
{
    $host = '';
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    }
    
    $host = strip_tags($host);
    
    return $host;
}

/**
 * 获取应用根目录
 * @return string
 */
function getRootPath()
{
    $root = dirname(_PHP_FILE_);
    if ($root == '/' || $root == '\\') {
        $root = '';
    } else {
        $root = rtrim($root, '/');
    }
    
    return $root;
}

/**
 * 载入配置 修改自ThinkPHP:C函数 为了不与tp冲突
 * @param  string              $name  配置名/文件名.
 * @param  string|array|object $value 配置赋值
 * @return void|null
 */
function sgconfig($name = null, $value = null)
{
    global $sg;
    // 无参数时获取所有
    if (empty($name)) {
        return $sg['_config'];
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtolower($name);
            if (is_null($value)) {
                return isset($sg['_config'][$name]) ? $sg['_config'][$name] : null;
            }
            $sg['_config'][$name] = $value;
            
            return;
        }
        // 二维数组设置和获取支持
        $name    = explode('.', $name);
        $name[0] = strtolower($name[0]);
        if (is_null($value)) {
            return isset($sg['_config'][$name[0]][$name[1]]) ? $sg['_config'][$name[0]][$name[1]] : null;
        }
        $sg['_config'][$name[0]][$name[1]] = $value;
        
        return;
    }
    // 批量设置
    if (is_array($name)) {
        return $sg['_config'] = array_merge((array) $sg['_config'], array_change_key_case($name));
    }
    
    return null;// 避免非法参数
}

/**
 * @title 载入全局配置
 * @param $path
 */
function load_sys_config($path)
{
    if (is_dir($path)) {
        $files = isset($path) ? scandir($path) : [];
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && '.' . pathinfo($file, PATHINFO_EXTENSION) == EXT) {
                $filename = $path . $file;
                if (file_exists($filename)) {
                    sgconfig(include $filename);
                }
            }
        }
    }
}

/**
 * 执行钩子方法
 * @param  string $name   钩子方法名.
 * @param  array  $params 钩子参数数组.
 * @return array|string Stripped array (or string in the callback).
 */
function sghook($name, $params = array())
{
    global $sg;
    $hooks = $sg['_config']['hooks'][$name];
    if ($hooks) {
        foreach ($hooks as $call) {
            if (is_callable($call)) {
                $result = call_user_func_array($call, $params);
            }
        }
        
        return $result;
    }
    
    return false;
}

/**
 * Navigates through an array and removes slashes from the values.
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 * @param  array|string $value The array or string to be striped.
 * @return array|string Stripped array (or string in the callback).
 */
function stripslashes_deep($value)
{
    if (is_array($value)) {
        $value = array_map('stripslashes_deep', $value);
    } elseif (is_object($value)) {
        $vars = get_object_vars($value);
        foreach ($vars as $key => $data) {
            $value->{$key} = stripslashes_deep($data);
        }
    } else {
        $value = stripslashes($value);
    }
    
    return $value;
}

/**
 * GPC参数过滤
 * @param  array|string $value The array or string to be striped.
 * @return array|string Stripped array (or string in the callback).
 */
function check_gpc($value = array())
{
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $data) {
        //对get、post的key值做限制,只允许数字、字母、及部分符号_-[]#@~
        // if(!preg_match('/^[a-zA-Z0-9,_\;\-\/\*\.#!@~\[\]]+$/i',$key)){
        // 	die('wrong_parameter: not safe get/post/cookie key.');
        // }
        //如果key值为app\mod\act,value只允许数字、字母下划线
        if (($key == 'app' || $key == 'mod' || $key == 'act') && !empty($data)) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/i', $data)) {
                die('wrong_parameter: not safe app/mod/act value.');
            }
        }
    }
}

/**
 * 返回16位md5值
 * @param  string $str 字符串
 * @return string $str 返回16位的字符串
 */
function sgmd5($str)
{
    return substr(md5($str), 8, 16);
}

/**
 * 全站静态缓存,替换之前每个model类中使用的静态缓存
 * 类似于s和f函数的使用
 * @param      $cache_id
 * @param null $value
 * @param bool $clean
 * @return array|bool|mixed
 */
function static_cache($cache_id, $value = null, $clean = false)
{
    static $cacheHash = array();
    if ($clean) { //清空缓存 其实是清不了的 程序执行结束才会自动清理
        unset($cacheHash);
        $cacheHash = array(0);
        
        return $cacheHash;
    }
    if (empty($cache_id)) {
        return false;
    }
    if ($value === null) {
        //获取缓存数据
        return isset($cacheHash[$cache_id]) ? $cacheHash[$cache_id] : false;
    } else {
        //设置缓存数据
        $cacheHash[$cache_id] = $value;
        
        return $cacheHash[$cache_id];
    }
}