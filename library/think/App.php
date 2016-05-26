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
     * @param null|\think\Request $request Request对象
     * @return mixed
     * @throws Exception
     */
    public static function run($request = null)
    {
        is_null($request) && $request = Request::instance();

        define('REQUEST_METHOD', $request->method());
        define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
        define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
        define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
        define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);
        define('IS_AJAX', $request->isAjax());
        define('__EXT__', $request->ext());

        // 初始化应用（公共模块）
        $config = self::initModule(COMMON_MODULE, Config::get());

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
        try {
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
            $type = IS_AJAX ? Config::get('default_ajax_return') : Config::get('default_return_type');
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
                $name = $param->getName();
                if (1 == $type && !empty($vars)) {
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
            $module = strtolower($result[0] ?: $config['default_module']);
            // 获取模块名称
            define('MODULE_NAME', strip_tags($module));
            $bind      = Route::bind('module');
            $available = false;
            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if (MODULE_NAME == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array(MODULE_NAME, $config['deny_module_list']) && is_dir(APP_PATH . MODULE_NAME)) {
                $available = true;
            }

            // 模块初始化
            if (MODULE_NAME && $available) {
                define('MODULE_PATH', APP_PATH . MODULE_NAME . DS);
                define('VIEW_PATH', MODULE_PATH . VIEW_LAYER . DS);
                // 初始化模块
                $config = self::initModule(MODULE_NAME, $config);
            } else {
                throw new Exception('module [ ' . MODULE_NAME . ' ] not exists ', 10005);
            }
        } else {
            // 单一模块部署
            define('MODULE_NAME', '');
            define('MODULE_PATH', APP_PATH);
            define('VIEW_PATH', MODULE_PATH . VIEW_LAYER . DS);
        }

        // 获取控制器名
        $controllerName = strip_tags($result[1] ?: $config['default_controller']);
        defined('CONTROLLER_NAME') or define('CONTROLLER_NAME', $config['url_controller_convert'] ? strtolower($controllerName) : $controllerName);

        // 获取操作名
        $actionName = strip_tags($result[2] ?: $config['default_action']);
        defined('ACTION_NAME') or define('ACTION_NAME', $config['url_action_convert'] ? strtolower($actionName) : $actionName);

        // 执行操作
        if (!preg_match('/^[A-Za-z](\/|\.|\w)*$/', CONTROLLER_NAME)) {
            // 安全检测
            throw new Exception('illegal controller name:' . CONTROLLER_NAME, 10000);
        }
        $instance = Loader::controller(CONTROLLER_NAME, '', $config['use_controller_suffix'], $config['empty_controller']);
        // 获取当前操作名
        $action = ACTION_NAME . $config['action_suffix'];

        try {
            // 操作方法开始监听
            $call = [$instance, $action];
            Hook::listen('action_begin', $call);
            if (!preg_match('/^[A-Za-z](\w)*$/', $action)) {
                // 非法操作
                throw new \ReflectionException('illegal action name :' . ACTION_NAME);
            }
            // 执行操作方法
            $data = self::invokeMethod($call);
        } catch (\ReflectionException $e) {
            // 操作不存在
            if (method_exists($instance, '_empty')) {
                $method = new \ReflectionMethod($instance, '_empty');
                $data   = $method->invokeArgs($instance, [$action, '']);
                APP_DEBUG && Log::record('[ RUN ] ' . $method->getFileName(), 'info');
            } else {
                throw new Exception('method [ ' . (new \ReflectionClass($instance))->getName() . '->' . $action . ' ] not exists ', 10002);
            }
        }
        return $data;
    }

    /**
     * 初始化模块
     * @access public
     * @param string $module 模块名
     * @param array $config 配置参数
     * @return void
     */
    private static function initModule($module, $config)
    {
        // 定位模块目录
        $module = (COMMON_MODULE == $module || !APP_MULTI_MODULE) ? '' : $module . DS;

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
                throw new Exception('route not define ');
            }
        }
        if (false === $result) {
            // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
            $result = Route::parseUrl($path, $depr, $config['controller_auto_search']);
        }
        //保证$_REQUEST正常取值
        $_REQUEST = array_merge($_POST, $_GET, $_COOKIE);
        // 注册调度机制
        return $request->dispatch($result);
    }

}
