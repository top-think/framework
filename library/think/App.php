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

namespace think;

use think\Config;
use think\Exception;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\Hook;
use think\Lang;
use think\Loader;
use think\Log;
use think\Request;
use think\Response;
use think\Route;

/**
 * App 应用管理
 * @author  liu21st <liu21st@gmail.com>
 */
class App
{
    /**
     * 执行应用程序
     * @access public
     * @param Request $request Request对象
     * @return mixed
     * @throws Exception
     */
    public static function run(Request $request = null)
    {
        is_null($request) && $request = Request::instance();

        // 初始化应用
        $config = self::init('', Config::get());

        // 注册根命名空间
        if (!empty($config['root_namespace'])) {
            Loader::addNamespace($config['root_namespace']);
        }

        // 加载额外文件
        if (!empty($config['extra_file_list'])) {
            foreach ($config['extra_file_list'] as $file) {
                $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                if (is_file($file)) {
                    include_once $file;
                }
            }
        }

        // 设置系统时区
        date_default_timezone_set($config['default_timezone']);

        try {
            // 监听app_init
            Hook::listen('app_init');

            // 开启多语言机制
            if ($config['lang_switch_on']) {
                // 获取当前语言
                defined('LANG_SET') or define('LANG_SET', Lang::range());
                // 加载系统语言包
                Lang::load(THINK_PATH . 'lang' . DS . LANG_SET . EXT);
                if (!APP_MULTI_MODULE) {
                    Lang::load(APP_PATH . 'lang' . DS . LANG_SET . EXT);
                }
            }

            // 获取当前请求的调度信息
            $dispatch = $request->dispatch();
            if (empty($dispatch)) {
                // 未指定调度类型 则进行URL路由检测
                $dispatch = self::route($request, $config);
            }
            // 记录路由信息
            APP_DEBUG && Log::record('[ ROUTE ] ' . var_export($dispatch, true), 'info');
            // 监听app_begin
            Hook::listen('app_begin', $dispatch);

            switch ($dispatch['type']) {
                case 'redirect':
                    // 执行重定向跳转
                    $data = Response::create($dispatch['url'], 'redirect')->code($dispatch['status']);
                    break;
                case 'module':
                    // 模块/控制器/操作
                    $data = self::module($dispatch['module'], $config);
                    break;
                case 'controller':
                    // 执行控制器操作
                    $data = Loader::action($dispatch['controller'], $dispatch['params']);
                    break;
                case 'method':
                    // 执行回调方法
                    $data = self::invokeMethod($dispatch['method'], $dispatch['params']);
                    break;
                case 'function':
                    // 执行闭包
                    $data = self::invokeFunction($dispatch['function'], $dispatch['params']);
                    break;
                case 'response':
                    $data = $dispatch['response'];
                    break;
                default:
                    throw new Exception('dispatch type not support', 10008);
            }
        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        }

        // 监听app_end
        Hook::listen('app_end', $data);

        // 输出数据到客户端
        if ($data instanceof Response) {
            return $data->send();
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $isAjax = $request->isAjax();
            $type   = $isAjax ? Config::get('default_ajax_return') : Config::get('default_return_type');
            return Response::create($data, $type)->send();
        }
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|array|\Closure $function 函数或者闭包
     * @param array $vars 变量
     * @return mixed
     */
    public static function invokeFunction($function, $vars = [])
    {
        $reflect = new \ReflectionFunction($function);
        $args    = self::bindParams($reflect, $vars);
        // 记录执行信息
        APP_DEBUG && Log::record('[ RUN ] ' . $reflect->getFileName() . '[ ' . var_export($vars, true) . ' ]', 'info');
        return $reflect->invokeArgs($args);
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param string|array $method 方法
     * @param array $vars 变量
     * @return mixed
     */
    public static function invokeMethod($method, $vars = [])
    {
        if (empty($vars)) {
            // 自动获取请求变量
            $vars = Request::instance()->param();
        }
        if (is_array($method)) {
            $class   = is_object($method[0]) ? $method[0] : new $method[0];
            $reflect = new \ReflectionMethod($class, $method[1]);
        } else {
            // 静态方法
            $reflect = new \ReflectionMethod($method);
        }
        $args = self::bindParams($reflect, $vars);
        // 记录执行信息
        APP_DEBUG && Log::record('[ RUN ] ' . $reflect->getFileName() . '[ ' . var_export($args, true) . ' ]', 'info');
        return $reflect->invokeArgs(isset($class) ? $class : null, $args);
    }

    /**
     * 绑定参数
     * @access public
     * @param \ReflectionMethod $reflect 反射类
     * @param array $vars 变量
     * @return array
     */
    private static function bindParams($reflect, $vars)
    {
        $args = [];
        // 判断数组类型 数字数组时按顺序绑定参数
        $type = key($vars) === 0 ? 1 : 0;
        if ($reflect->getNumberOfParameters() > 0) {
            $params = $reflect->getParameters();
            foreach ($params as $param) {
                $name  = $param->getName();
                $class = $param->getClass();
                if ($class && 'think\Request' == $class->getName()) {
                    $args[] = Request::instance();
                } elseif (1 == $type && !empty($vars)) {
                    $args[] = array_shift($vars);
                } elseif (0 == $type && isset($vars[$name])) {
                    $args[] = $vars[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new Exception('method param miss:' . $name, 10004);
                }
            }
            // 全局过滤
            array_walk_recursive($args, 'think\\Input::filterExp');
        }
        return $args;
    }

    /**
     * 执行模块
     * @access public
     * @param array $result 模块/控制器/操作
     * @param array $config 配置参数
     * @return mixed
     */
    public static function module($result, $config)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }
        if (APP_MULTI_MODULE) {
            // 多模块部署
            $module    = strip_tags(strtolower($result[0] ?: $config['default_module']));
            $bind      = Route::bind('module');
            $available = false;
            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['deny_module_list']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $config = self::init($module, $config);
            } else {
                throw new HttpException(404, 'module [ ' . $module . ' ] not exists ');
            }
        } else {
            // 单一模块部署
            $module = '';
        }
        // 当前模块路径
        define('MODULE_PATH', APP_PATH . ($module ? $module . DS : ''));

        // 获取控制器名
        $controller = strip_tags($result[1] ?: $config['default_controller']);
        $controller = $config['url_controller_convert'] ? strtolower($controller) : $controller;

        // 获取操作名
        $actionName = strip_tags($result[2] ?: $config['default_action']);
        $actionName = $config['url_action_convert'] ? strtolower($actionName) : $actionName;

        // 执行操作
        if (!preg_match('/^[A-Za-z](\/|\.|\w)*$/', $controller)) {
            // 安全检测
            throw new Exception('illegal controller name:' . $controller, 10000);
        }

        // 设置当前请求的模块、控制器、操作
        $request = Request::instance();
        $request->module($module)->controller($controller)->action($actionName);

        // 监听module_init
        Hook::listen('module_init', $request);

        try {
            $instance = Loader::controller($controller, $config['url_controller_layer'], $config['use_controller_suffix'], $config['empty_controller']);

            // 获取当前操作名
            $action = $actionName . $config['action_suffix'];
            if (!preg_match('/^[A-Za-z](\w)*$/', $action)) {
                // 非法操作
                throw new \ReflectionException('illegal action name :' . $actionName);
            }

            // 执行操作方法
            $call = [$instance, $action];
            Hook::listen('action_begin', $call);

            $data = self::invokeMethod($call);
        } catch (\ReflectionException $e) {
            // 操作不存在
            if (method_exists($instance, '_empty')) {
                $method = new \ReflectionMethod($instance, '_empty');
                $data   = $method->invokeArgs($instance, [$action, '']);
                APP_DEBUG && Log::record('[ RUN ] ' . $method->getFileName(), 'info');
            } else {
                throw new HttpException(404, 'method [ ' . (new \ReflectionClass($instance))->getName() . '->' . $action . ' ] not exists ');
            }
        }
        return $data;
    }

    /**
     * 初始化应用或模块
     * @access public
     * @param string $module 模块名
     * @param array $config 配置参数
     * @return void
     */
    private static function init($module, $config)
    {
        // 定位模块目录
        $module = ($module && APP_MULTI_MODULE) ? $module . DS : '';

        // 加载初始化文件
        if (is_file(APP_PATH . $module . 'init' . EXT)) {
            include APP_PATH . $module . 'init' . EXT;
        } else {
            $path = APP_PATH . $module;
            // 加载模块配置
            $config = Config::load(CONF_PATH . $module . 'config' . CONF_EXT);

            // 加载应用状态配置
            if ($config['app_status']) {
                $config = Config::load(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
            }

            // 读取扩展配置文件
            if ($config['extra_config_list']) {
                foreach ($config['extra_config_list'] as $name => $file) {
                    $filename = CONF_PATH . $module . $file . CONF_EXT;
                    Config::load($filename, is_string($name) ? $name : pathinfo($filename, PATHINFO_FILENAME));
                }
            }

            // 加载别名文件
            if (is_file(CONF_PATH . $module . 'alias' . EXT)) {
                Loader::addMap(include CONF_PATH . $module . 'alias' . EXT);
            }

            // 加载行为扩展文件
            if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
                Hook::import(include CONF_PATH . $module . 'tags' . EXT);
            }

            // 加载公共文件
            if (is_file($path . 'common' . EXT)) {
                include $path . 'common' . EXT;
            }

            // 加载当前模块语言包
            if ($config['lang_switch_on'] && $module) {
                Lang::load($path . 'lang' . DS . LANG_SET . EXT);
            }
        }
        return Config::get();
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @param  \think\Request $request
     * @param  array $config
     * @return array
     * @throws Exception
     */
    public static function route($request, array $config)
    {
        // 检测URL禁用后缀
        if ($config['url_deny_suffix'] && preg_match('/\.(' . $config['url_deny_suffix'] . ')$/i', $request->pathinfo())) {
            throw new Exception('url suffix deny');
        }

        $path   = $request->path();
        $depr   = $config['pathinfo_depr'];
        $result = false;
        // 路由检测
        if (APP_ROUTE_ON && !empty($config['url_route_on'])) {
            // 开启路由
            if (!empty($config['route'])) {
                // 导入路由配置
                Route::import($config['route']);
            }
            // 路由检测（根据路由定义返回不同的URL调度）
            $result = Route::check($request, $path, $depr, !IS_CLI ? $config['url_domain_deploy'] : false);
            if (APP_ROUTE_MUST && false === $result && $config['url_route_must']) {
                // 路由无效
                throw new HttpException(404, 'Not Found');
            }
        }
        if (false === $result) {
            // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
            $result = Route::parseUrl($path, $depr, $config['controller_auto_search'], $config['url_param_type']);
        }
        //保证$_REQUEST正常取值
        $_REQUEST = array_merge($_POST, $_GET, $_COOKIE);
        // 注册调度机制
        return $request->dispatch($result);
    }

}
