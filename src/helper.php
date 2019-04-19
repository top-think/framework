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

//------------------------
// ThinkPHP 助手函数
//-------------------------

use think\App;
use think\Container;
use think\db\Raw;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Env;
use think\facade\Event;
use think\facade\Lang;
use think\facade\Log;
use think\facade\Request;
use think\facade\Route;
use think\facade\Session;
use think\Model;
use think\model\Collection as ModelCollection;
use think\Response;
use think\route\RuleItem;
use think\Validate;

if (!function_exists('abort')) {
    /**
     * 抛出HTTP异常
     * @param integer|Response $code    状态码 或者 Response对象实例
     * @param string           $message 错误信息
     * @param array            $header  参数
     */
    function abort($code, string $message = null, array $header = [])
    {
        if ($code instanceof Response) {
            throw new HttpResponseException($code);
        } else {
            throw new HttpException($code, $message, null, $header);
        }
    }
}

if (!function_exists('app')) {
    /**
     * 快速获取容器中的实例 支持依赖注入
     * @param string $name        类名或标识 默认获取当前应用实例
     * @param array  $args        参数
     * @param bool   $newInstance 是否每次创建新的实例
     * @return object|App
     */
    function app(string $name = App::class, array $args = [], bool $newInstance = false)
    {
        return Container::pull($name, $args, $newInstance);
    }
}

if (!function_exists('bind')) {
    /**
     * 绑定一个类到容器
     * @param  string|array $abstract 类标识、接口（支持批量绑定）
     * @param  mixed        $concrete 要绑定的类、闭包或者实例
     * @return Container
     */
    function bind($abstract, $concrete = null)
    {
        return Container::getInstance()->bind($abstract, $concrete);
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存管理
     * @param  mixed  $name    缓存名称，如果为数组表示进行缓存设置
     * @param  mixed  $value   缓存值
     * @param  mixed  $options 缓存参数
     * @param  string $tag     缓存标签
     * @return mixed
     */
    function cache($name, $value = '', $options = null, $tag = null)
    {
        if (is_array($name)) {
            // 缓存初始化
            return Cache::connect($name);
        }

        if ('' === $value) {
            // 获取缓存
            return 0 === strpos($name, '?') ? Cache::has(substr($name, 1)) : Cache::get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return Cache::rm($name);
        }

        // 缓存数据
        if (is_array($options)) {
            $expire = $options['expire'] ?? null; //修复查询缓存无法设置过期时间
        } else {
            $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
        }

        if (is_null($tag)) {
            return Cache::set($name, $value, $expire);
        } else {
            return Cache::tag($tag)->set($name, $value, $expire);
        }
    }
}

if (!function_exists('call')) {
    /**
     * 调用反射执行callable 支持依赖注入
     * @param  mixed $callable 支持闭包等callable写法
     * @param  array $args     参数
     * @return mixed
     */
    function call(callable $callable, array $args = [])
    {
        return Container::getInstance()->invoke($callable, $args);
    }
}

if (!function_exists('class_basename')) {
    /**
     * 获取类名(不包含命名空间)
     *
     * @param  mixed $class 类名
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     *获取一个类里所有用到的trait，包括父类的
     *
     * @param  mixed $class 类名
     * @return array
     */
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        $classes = array_merge([$class => $class], class_parents($class));
        foreach ($classes as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('config')) {
    /**
     * 获取和设置配置参数
     * @param  string|array $name  参数名
     * @param  mixed        $value 参数值
     * @return mixed
     */
    function config($name = '', $value = null)
    {
        if (is_array($name)) {
            return Config::set($name, $value);
        }

        return 0 === strpos($name, '?') ? Config::has(substr($name, 1)) : Config::get($name, $value);
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie管理
     * @param  string $name   cookie名称
     * @param  mixed  $value  cookie值
     * @param  mixed  $option 参数
     * @return mixed
     */
    function cookie(string $name, $value = '', $option = null)
    {
        if (is_null($value)) {
            // 删除
            Cookie::delete($name);
        } elseif ('' === $value) {
            // 获取
            return 0 === strpos($name, '?') ? Request::has(substr($name, 1), 'cookie') : Request::cookie($name);
        } else {
            // 设置
            return Cookie::set($name, $value, $option);
        }
    }
}

if (!function_exists('download')) {
    /**
     * 获取\think\response\Download对象实例
     * @param  string $filename 要下载的文件
     * @param  string $name     显示文件名
     * @param  bool   $content  是否为内容
     * @param  int    $expire   有效期（秒）
     * @return \think\response\File
     */
    function download(string $filename, string $name = '', bool $content = false, int $expire = 180)
    {
        return Response::create($filename, 'file')->name($name)->isContent($content)->expire($expire);
    }
}

if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     * @param  mixed  $var   变量
     * @param  bool   $echo  是否输出 默认为true 如果为false 则返回输出字符串
     * @param  string $label 标签 默认为空
     * @return void|string
     */
    function dump($var, bool $echo = true, string $label = null)
    {
        $label = (null === $label) ? '' : rtrim($label) . ':';
        if ($var instanceof Model || $var instanceof ModelCollection) {
            $var = $var->toArray();
        }

        ob_start();
        var_dump($var);

        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (PHP_SAPI == 'cli') {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, ENT_SUBSTITUTE);
            }
            $output = '<pre>' . $label . $output . '</pre>';
        }

        if ($echo) {
            echo $output;
            return;
        }

        return $output;
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量值
     * @access public
     * @param  string $name    环境变量名（支持二级 .号分割）
     * @param  string $default 默认值
     * @return mixed
     */
    function env(string $name = null, $default = null)
    {
        return Env::get($name, $default);
    }
}

if (!function_exists('event')) {
    /**
     * 触发事件
     * @param  mixed $event 事件名（或者类名）
     * @param  mixed $args  参数
     * @return mixed
     */
    function event($event, $args = null)
    {
        return Event::trigger($event, $args);
    }
}

if (!function_exists('exception')) {
    /**
     * 抛出异常处理
     *
     * @param string $msg       异常消息
     * @param int    $code      异常代码 默认为0
     * @param string $exception 异常类
     *
     * @throws Exception
     */
    function exception(string $msg, int $code = 0, string $exception = '')
    {
        $e = $exception ?: '\think\Exception';
        throw new $e($msg, $code);
    }
}

if (!function_exists('halt')) {
    /**
     * 调试变量并且中断输出
     * @param mixed $var 调试变量或者信息
     */
    function halt($var)
    {
        dump($var);

        throw new HttpResponseException(new Response);
    }
}

if (!function_exists('input')) {
    /**
     * 获取输入数据 支持默认值和过滤
     * @param  string $key     获取的变量名
     * @param  mixed  $default 默认值
     * @param  string $filter  过滤方法
     * @return mixed
     */
    function input(string $key = '', $default = null, $filter = '')
    {
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }

        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            $method = substr($key, 0, $pos);
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'env', 'path', 'file'])) {
                $key = substr($key, $pos + 1);
            } else {
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }

        return isset($has) ?
        request()->has($key, $method) :
        request()->$method($key, $default, $filter);
    }
}

if (!function_exists('json')) {
    /**
     * 获取\think\response\Json对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Json
     */
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code)->header($header)->options($options);
    }
}

if (!function_exists('jsonp')) {
    /**
     * 获取\think\response\Jsonp对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Jsonp
     */
    function jsonp($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'jsonp', $code)->header($header)->options($options);
    }
}

if (!function_exists('lang')) {
    /**
     * 获取语言变量值
     * @param  string $name 语言变量名
     * @param  array  $vars 动态变量值
     * @param  string $lang 语言
     * @return mixed
     */
    function lang(string $name, array $vars = [], string $lang = '')
    {
        return Lang::get($name, $vars, $lang);
    }
}

if (!function_exists('parse_name')) {
    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param  string $name    字符串
     * @param  int    $type    转换类型
     * @param  bool   $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    function parse_name(string $name, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

if (!function_exists('raw')) {
    /**
     * 生成一个数据库的Raw对象
     * @param  string $sql SQL指令
     * @return \think\db\Raw
     */
    function raw(string $sql): Raw
    {
        return Db::raw($sql);
    }
}

if (!function_exists('redirect')) {
    /**
     * 获取\think\response\Redirect对象实例
     * @param  mixed         $url    重定向地址 支持Url::build方法的地址
     * @param  array|integer $params 额外参数
     * @param  int           $code   状态码
     * @return \think\response\Redirect
     */
    function redirect($url = [], $params = [], $code = 302)
    {
        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }

        return Response::create($url, 'redirect', $code)->params($params);
    }
}

if (!function_exists('request')) {
    /**
     * 获取当前Request对象实例
     * @return Request
     */
    function request()
    {
        return app('request');
    }
}

if (!function_exists('response')) {
    /**
     * 创建普通 Response 对象实例
     * @param  mixed      $data   输出数据
     * @param  int|string $code   状态码
     * @param  array      $header 头信息
     * @param  string     $type
     * @return Response
     */
    function response($data = '', $code = 200, $header = [], $type = 'html')
    {
        return Response::create($data, $type, $code)->header($header);
    }
}

if (!function_exists('route')) {
    /**
     * 路由注册
     * @param  string $rule   路由规则
     * @param  mixed  $route  路由地址
     * @param  string $method 请求类型
     * @return RuleItem
     */
    function route(string $rule, $route, $method = '*'): RuleItem
    {
        return Route::rule($rule, $route, $method);
    }
}

if (!function_exists('session')) {
    /**
     * Session管理
     * @param  string $name  session名称
     * @param  mixed  $value session值
     * @return mixed
     */
    function session(string $name = null, $value = '')
    {
        if (is_null($name)) {
            // 清除
            Session::clear();
        } elseif (is_null($value)) {
            // 删除
            Session::delete($name);
        } elseif ('' === $value) {
            // 判断或获取
            return 0 === strpos($name, '?') ? Session::has(substr($name, 1)) : Session::get($name);
        } else {
            // 设置
            Session::set($name, $value);
        }
    }
}

if (!function_exists('token')) {
    /**
     * 生成表单令牌
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    function token(string $name = '__token__', string $type = 'md5'): string
    {
        $token = Request::token($name, $type);

        return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
    }
}

if (!function_exists('trace')) {
    /**
     * 记录日志信息
     * @param  mixed  $log   log信息 支持字符串和数组
     * @param  string $level 日志级别
     * @return array|void
     */
    function trace($log = '[think]', string $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        }

        Log::record($log, $level);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * 获取一个trait里所有引用到的trait
     *
     * @param  string $trait Trait
     * @return array
     */
    function trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait);
        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (!function_exists('url')) {
    /**
     * Url生成
     * @param string      $url    路由地址
     * @param array       $vars   变量
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return string
     */
    function url(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return Route::buildUrl($url, $vars, $suffix, $domain);
    }
}

if (!function_exists('validate')) {
    /**
     * 验证数据
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return bool
     * @throws ValidateException
     */
    function validate(array $data, $validate, array $message = [], bool $batch = false): bool
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                list($validate, $scene) = explode('.', $validate);
            }

            $class = app()->parseClass('validate', $validate);
            $v     = new $class();

            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        return $v->message($message)->batch($batch)->failException(true)->check($data);
    }
}

if (!function_exists('view')) {
    /**
     * 渲染模板输出
     * @param string    $template 模板文件
     * @param array     $vars 模板变量
     * @param int       $code 状态码
     * @param callable  $filter 内容过滤
     * @return \think\response\View
     */
    function view(string $template = '', $vars = [], $code = 200, $filter = null)
    {
        return Response::create($template, 'view', $code)->assign($vars)->filter($filter);
    }
}

if (!function_exists('xml')) {
    /**
     * 获取\think\response\Xml对象实例
     * @param  mixed $data    返回的数据
     * @param  int   $code    状态码
     * @param  array $header  头部
     * @param  array $options 参数
     * @return \think\response\Xml
     */
    function xml($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'xml', $code)->header($header)->options($options);
    }
}

if (!function_exists('yaconf')) {
    /**
     * 获取yaconf配置
     *
     * @param  string $name    配置参数名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    function yaconf(string $name, $default = null)
    {
        return Config::yaconf($name, $default);
    }
}
