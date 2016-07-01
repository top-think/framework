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

//------------------------
// ThinkPHP 助手函数
//-------------------------

use think\Cache;
use think\Config;
use think\Cookie;
use think\Db;
use think\Debug;
use think\Lang;
use think\Loader;
use think\Log;
use think\Request;
use think\Response;
use think\Session;
use think\Url;
use think\View;

/**
 * 快速导入Traits PHP5.5以上无需调用
 * @param string    $class trait库
 * @param string    $ext 类库后缀
 * @return boolean
 */
if (!function_exists('load_trait')) {
    function load_trait($class, $ext = EXT)
    {
        return Loader::import($class, TRAIT_PATH, $ext);
    }
}

/**
 * 抛出异常处理
 *
 * @param string    $msg  异常消息
 * @param integer   $code 异常代码 默认为0
 * @param string    $exception 异常类
 *
 * @throws Exception
 */
if (!function_exists('exception')) {
    function exception($msg, $code = 0, $exception = '')
    {
        $e = $exception ?: '\think\Exception';
        throw new $e($msg, $code);
    }
}

/**
 * 记录时间（微秒）和内存使用情况
 * @param string            $start 开始标签
 * @param string            $end 结束标签
 * @param integer|string    $dec 小数位 如果是m 表示统计内存占用
 * @return mixed
 */
if (!function_exists('debug')) {
    function debug($start, $end = '', $dec = 6)
    {
        if ('' == $end) {
            Debug::remark($start);
        } else {
            return 'm' == $dec ? Debug::getRangeMem($start, $end) : Debug::getRangeTime($start, $end, $dec);
        }
    }
}

/**
 * 获取语言变量值
 * @param string    $name 语言变量名
 * @param array     $vars 动态变量值
 * @param string    $lang 语言
 * @return mixed
 */
if (!function_exists('lang')) {
    function lang($name, $vars = [], $lang = '')
    {
        return Lang::get($name, $vars, $lang);
    }
}

/**
 * 获取和设置配置参数
 * @param string|array  $name 参数名
 * @param mixed         $value 参数值
 * @param string        $range 作用域
 * @return mixed
 */
if (!function_exists('config')) {
    function config($name = '', $value = null, $range = '')
    {
        if (is_null($value) && is_string($name)) {
            return Config::get($name, $range);
        } else {
            return Config::set($name, $value, $range);
        }
    }
}

/**
 * 获取输入数据 支持默认值和过滤
 * @param string    $key 获取的变量名
 * @param mixed     $default 默认值
 * @param string    $filter 过滤方法
 * @return mixed
 */
if (!function_exists('input')) {
    function input($key, $default = null, $filter = null)
    {
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }
        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            $method = substr($key, 0, $pos);
            if (in_array($method, ['get', 'post', 'put', 'delete', 'param', 'request', 'session', 'cookie', 'server', 'env', 'path', 'file'])) {
                $key = substr($key, $pos + 1);
            } else {
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }
        if (isset($has)) {
            return request()->has($key, $method, $default);
        } else {
            return request()->$method($key, $default, $filter);
        }
    }
}

/**
 * 渲染输出Widget
 * @param string    $name Widget名称
 * @param array     $data 传人的参数
 * @return mixed
 */
if (!function_exists('widget')) {
    function widget($name, $data = [])
    {
        return Loader::action($name, $data, 'widget');
    }
}

/**
 * 实例化Model
 * @param string    $name Model名称
 * @param string    $layer 业务层名称
 * @param bool      $appendSuffix 是否添加类名后缀
 * @return \think\Model
 */
if (!function_exists('model')) {
    function model($name = '', $layer = 'model', $appendSuffix = false)
    {
        return Loader::model($name, $layer, $appendSuffix);
    }
}

/**
 * 实例化验证器
 * @param string    $name 验证器名称
 * @param string    $layer 业务层名称
 * @param bool      $appendSuffix 是否添加类名后缀
 * @return \think\Validate
 */
if (!function_exists('validate')) {
    function validate($name = '', $layer = 'validate', $appendSuffix = false)
    {
        return Loader::validate($name, $layer, $appendSuffix);
    }
}

/**
 * 实例化数据库类
 * @param string        $name 操作的数据表名称（不含前缀）
 * @param array|string  $config 数据库配置参数
 * @return \think\db\Query
 */
if (!function_exists('db')) {
    function db($name = '', $config = [])
    {
        return Db::connect($config)->name($name);
    }
}

/**
 * 实例化控制器 格式：[模块/]控制器
 * @param string    $name 资源地址
 * @param string    $layer 控制层名称
 * @param bool      $appendSuffix 是否添加类名后缀
 * @return \think\Controller
 */
if (!function_exists('controller')) {
    function controller($name, $layer = 'controller', $appendSuffix = false)
    {
        return Loader::controller($name, $layer, $appendSuffix);
    }
}

/**
 * 调用模块的操作方法 参数格式 [模块/控制器/]操作
 * @param string        $url 调用地址
 * @param string|array  $vars 调用参数 支持字符串和数组
 * @param string        $layer 要调用的控制层名称
 * @param bool          $appendSuffix 是否添加类名后缀
 * @return mixed
 */
if (!function_exists('action')) {
    function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        return Loader::action($url, $vars, $layer, $appendSuffix);
    }
}

/**
 * 导入所需的类库 同java的Import 本函数有缓存功能
 * @param string    $class 类库命名空间字符串
 * @param string    $baseUrl 起始路径
 * @param string    $ext 导入的文件扩展名
 * @return boolean
 */
if (!function_exists('import')) {
    function import($class, $baseUrl = '', $ext = EXT)
    {
        return Loader::import($class, $baseUrl, $ext);
    }
}

/**
 * 快速导入第三方框架类库 所有第三方框架的类库文件统一放到 系统的Vendor目录下面
 * @param string    $class 类库
 * @param string    $ext 类库后缀
 * @return boolean
 */
if (!function_exists('vendor')) {
    function vendor($class, $ext = EXT)
    {
        return Loader::import($class, VENDOR_PATH, $ext);
    }
}

/**
 * 浏览器友好的变量输出
 * @param mixed     $var 变量
 * @param boolean   $echo 是否输出 默认为true 如果为false 则返回输出字符串
 * @param string    $label 标签 默认为空
 * @return void|string
 */
if (!function_exists('dump')) {
    function dump($var, $echo = true, $label = null)
    {
        return Debug::dump($var, $echo, $label);
    }
}

/**
 * Url生成
 * @param string        $url 路由地址
 * @param string|array  $value 变量
 * @param bool|string   $suffix 前缀
 * @param bool|string   $domain 域名
 * @return string
 */
if (!function_exists('url')) {
    function url($url = '', $vars = '', $suffix = true, $domain = false)
    {
        return Url::build($url, $vars, $suffix, $domain);
    }
}

/**
 * Session管理
 * @param string|array  $name session名称，如果为数组表示进行session设置
 * @param mixed         $value session值
 * @param string        $prefix 前缀
 * @return mixed
 */
if (!function_exists('session')) {
    function session($name, $value = '', $prefix = null)
    {
        if (is_array($name)) {
            // 初始化
            Session::init($name);
        } elseif (is_null($name)) {
            // 清除
            Session::clear($value);
        } elseif ('' === $value) {
            // 判断或获取
            return 0 === strpos($name, '?') ? Session::has(substr($name, 1), $prefix) : Session::get($name, $prefix);
        } elseif (is_null($value)) {
            // 删除
            return Session::delete($name, $prefix);
        } else {
            // 设置
            return Session::set($name, $value, $prefix);
        }
    }
}

/**
 * Cookie管理
 * @param string|array  $name cookie名称，如果为数组表示进行cookie设置
 * @param mixed         $value cookie值
 * @param mixed         $option 参数
 * @return mixed
 */
if (!function_exists('cookie')) {
    function cookie($name, $value = '', $option = null)
    {
        if (is_array($name)) {
            // 初始化
            Cookie::init($name);
        } elseif (is_null($name)) {
            // 清除
            Cookie::clear($value);
        } elseif ('' === $value) {
            // 获取
            return Cookie::get($name);
        } elseif (is_null($value)) {
            // 删除
            return Cookie::delete($name);
        } else {
            // 设置
            return Cookie::set($name, $value, $option);
        }
    }
}

/**
 * 缓存管理
 * @param mixed     $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed     $value 缓存值
 * @param mixed     $options 缓存参数
 * @return mixed
 */
if (!function_exists('cache')) {
    function cache($name, $value = '', $options = null)
    {
        if (is_array($options)) {
            // 缓存操作的同时初始化
            Cache::connect($options);
        } elseif (is_array($name)) {
            // 缓存初始化
            return Cache::connect($name);
        }
        if ('' === $value) {
            // 获取缓存
            return Cache::get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return Cache::rm($name);
        } else {
            // 缓存数据
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : null; //修复查询缓存无法设置过期时间
            } else {
                $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
            }
            return Cache::set($name, $value, $expire);
        }
    }
}

/**
 * 记录日志信息
 * @param mixed     $log log信息 支持字符串和数组
 * @param string    $level 日志级别
 * @return void|array
 */
if (!function_exists('trace')) {
    function trace($log = '[think]', $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        } else {
            Log::record($log, $level);
        }
    }
}

/**
 * 获取当前Request对象实例
 * @return Request
 */
if (!function_exists('request')) {
    function request()
    {
        return Request::instance();
    }
}

/**
 * 创建普通 Response 对象实例
 * @param mixed      $data   输出数据
 * @param int|string $code   状态码
 * @param array      $header 头信息
 * @param string     $type
 * @return Response
 */
if (!function_exists('response')) {
    function response($data = [], $code = 200, $header = [], $type = 'html')
    {
        return Response::create($data, $type, $code, $header);
    }
}

/**
 * 渲染模板输出
 * @param string    $template 模板文件
 * @param array     $vars 模板变量
 * @param integer   $code 状态码
 * @return \think\response\View
 */
if (!function_exists('view')) {
    function view($template = '', $vars = [], $code = 200)
    {
        return Response::create($template, 'view', $code)->vars($vars);
    }
}

/**
 * 获取\think\response\Json对象实例
 * @param mixed   $data 返回的数据
 * @param integer $code 状态码
 * @param array   $header 头部
 * @param array   $options 参数
 * @return \think\response\Json
 */
if (!function_exists('json')) {
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code, $header, $options);
    }
}

/**
 * 获取\think\response\Jsonp对象实例
 * @param mixed   $data    返回的数据
 * @param integer $code    状态码
 * @param array   $header 头部
 * @param array   $options 参数
 * @return \think\response\Jsonp
 */
if (!function_exists('jsonp')) {
    function jsonp($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'jsonp', $code, $header, $options);
    }
}

/**
 * 获取\think\response\Xml对象实例
 * @param mixed   $data    返回的数据
 * @param integer $code    状态码
 * @param array   $header  头部
 * @param array   $options 参数
 * @return \think\response\Xml
 */
if (!function_exists('xml')) {
    function xml($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'xml', $code, $header, $options);
    }
}

/**
 * 获取\think\response\Redirect对象实例
 * @param mixed         $url 重定向地址 支持Url::build方法的地址
 * @param array|integer $params 额外参数
 * @param integer       $code 状态码
 * @return \think\response\Redirect
 */
if (!function_exists('redirect')) {
    function redirect($url = [], $params = [], $code = 302)
    {
        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }
        return Response::create($url, 'redirect', $code)->params($params);
    }
}

/**
 * 抛出HTTP异常
 * @param integer   $code 状态码
 * @param string    $message 错误信息
 * @param array     $header 参数
 */
if (!function_exists('abort')) {
    function abort($code, $message = null, $header = [])
    {
        throw new \think\exception\HttpException($code, $message, null, $header);
    }
}
