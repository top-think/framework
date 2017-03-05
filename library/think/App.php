<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\RouteNotFoundException;

/**
 * App 应用管理
 * @author  liu21st <liu21st@gmail.com>
 */
class App extends Container
{
    const VERSION = '5.1.0alpha';
    /**
     * @var bool 是否初始化过
     */
    protected $init = false;

    /**
     * @var string 当前模块路径
     */
    protected $modulePath;

    /**
     * @var bool 应用调试模式
     */
    protected $debug = true;

    /**
     * @var string 应用类库命名空间
     */
    protected $namespace = 'app';

    /**
     * @var bool 应用类库后缀
     */
    protected $suffix = false;

    /**
     * @var bool 应用路由检测
     */
    protected $routeCheck;

    /**
     * @var bool 严格路由检测
     */
    protected $routeMust;

    protected $dispatch;
    protected $file = [];
    protected $config;

    public function version()
    {
        return static::VERSION;
    }

    public function isDebug()
    {
        return $this->debug;
    }

    public function getModulePath()
    {
        return $this->modulePath;
    }

    public function setModulePath($path)
    {
        $this->modulePath = $path;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function getSuffix()
    {
        return $this->suffix;
    }

    public function __construct(Config $config)
    {
        Container::getInstance()->bind([
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

        $this->config = $config;

    }

    /**
     * 执行应用程序
     * @access public
     * @param Request $request Request对象
     * @return Response
     * @throws Exception
     */
    public function run(Request $request = null)
    {
        is_null($request) && $request = Facade::make('request');

        try {
            $config = $this->initCommon();
            if (defined('BIND_MODULE')) {
                // 模块/控制器绑定
                BIND_MODULE && Facade::make('route')->bind(BIND_MODULE);
            } elseif ($config['auto_bind_module']) {
                // 入口自动绑定
                $name = pathinfo($request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir(APP_PATH . $name)) {
                    Facade::make('route')->bind($name);
                }
            }

            $request->filter($config['default_filter']);
            $lang = Facade::make('lang');
            if ($config['lang_switch_on']) {
                // 开启多语言机制 检测当前语言
                $lang->detect();
            } else {
                // 读取默认语言
                $lang->range($config['default_lang']);
            }
            $request->langset($lang->range());
            // 加载系统语言包
            $lang->load([
                THINK_PATH . 'lang' . DS . $request->langset() . EXT,
                APP_PATH . 'lang' . DS . $request->langset() . EXT,
            ]);

            // 获取应用调度信息
            $dispatch = $this->dispatch;
            if (empty($dispatch)) {
                // 进行URL路由检测
                $dispatch = $this->routeCheck($request, $config);
            }
            // 记录当前调度信息
            $request->dispatch($dispatch);

            // 记录路由和请求信息
            if ($this->debug) {
                $this->log('[ ROUTE ] ' . var_export($dispatch, true));
                $this->log('[ HEADER ] ' . var_export($request->header(), true));
                $this->log('[ PARAM ] ' . var_export($request->param(), true));
            }

            // 监听app_begin
            Facade::make('hook')->listen('app_begin', $dispatch);
            // 请求缓存检查
            $request->cache($config['request_cache'], $config['request_cache_expire'], $config['request_cache_except']);

            $data = $this->exec($request, $dispatch, $config);

        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        }

        // 清空类的实例化
        Loader::clearInstance();

        // 输出数据到客户端
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $isAjax   = $request->isAjax();
            $type     = $isAjax ? $this->config('default_ajax_return') : $this->config('default_return_type');
            $response = Response::create($data, $type);
        } else {
            $response = Response::create();
        }

        // 监听app_end
        Facade::make('hook')->listen('app_end', $response);

        return $response;
    }

    public function exec($request, $dispatch, $config)
    {
        switch ($dispatch['type']) {
            case 'redirect':
                // 执行重定向跳转
                $data = Response::create($dispatch['url'], 'redirect')->code($dispatch['status']);
                break;
            case 'module':
                // 模块/控制器/操作
                $data = $this->module($request, $dispatch['module'], $config, isset($dispatch['convert']) ? $dispatch['convert'] : null);
                break;
            case 'controller':
                // 执行控制器操作
                $vars = array_merge($request->param(), $dispatch['var']);
                $data = Loader::action($dispatch['controller'], $vars, $config['url_controller_layer'], $config['controller_suffix']);
                break;
            case 'method':
                // 执行回调方法
                $vars = array_merge($request->param(), $dispatch['var']);
                $data = $this->invokeMethod($dispatch['method'], $vars);
                break;
            case 'function':
                // 执行闭包
                $data = $this->invokeFunction($dispatch['function']);
                break;
            case 'response':
                $data = $dispatch['response'];
                break;
            default:
                throw new \InvalidArgumentException('dispatch type not support');
        }
        return $data;
    }

    /**
     * 设置当前请求的调度信息
     * @access public
     * @param array|string  $dispatch 调度信息
     * @param string        $type 调度类型
     * @return void
     */
    public function dispatch($dispatch, $type = 'module')
    {
        $this->dispatch = ['type' => $type, $type => $dispatch];
    }

    /**
     * 记录调试信息
     * @param mixed  $msg  调试信息
     * @param string $type 信息类型
     * @return void
     */
    public function log($log, $type = 'info')
    {
        $this->debug && Facade::make('log')->record($log, $type);
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @param string    $name 配置参数名（支持二级配置 .号分割）
     * @return mixed
     */
    public function config($name = '')
    {
        return $this->config->get($name);
    }

    /**
     * 执行模块
     * @access public
     * @param Request $request 当前请求对象实例
     * @param array $result 模块/控制器/操作
     * @param array $config 配置参数
     * @param bool  $convert 是否自动转换控制器和操作名
     * @return mixed
     */
    public function module($request, $result, $config, $convert = null)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }

        if ($config['app_multi_module']) {
            // 多模块部署
            $module    = strip_tags(strtolower($result[0] ?: $config['default_module']));
            $bind      = Facade::make('route')->getBind('module');
            $available = false;
            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if (empty($result[0])) {
                    $module    = $bindModule;
                    $available = true;
                } elseif ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['deny_module_list']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $request->module($module);
                $config = $this->init($module);
                // 模块请求缓存检查
                $request->cache($config['request_cache'], $config['request_cache_expire'], $config['request_cache_except']);
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        } else {
            // 单一模块部署
            $module = '';
            $request->module($module);
        }
        // 当前模块路径
        $this->modulePath = APP_PATH . ($module ? $module . DS : '');

        // 是否自动转换控制器和操作名
        $convert = is_bool($convert) ? $convert : $config['url_convert'];
        // 获取控制器名
        $controller = strip_tags($result[1] ?: $config['default_controller']);
        $controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $actionName = strip_tags($result[2] ?: $config['default_action']);
        $actionName = $convert ? strtolower($actionName) : $actionName;

        // 设置当前请求的控制器、操作
        $request->controller(Loader::parseName($controller, 1))->action($actionName);

        // 监听module_init
        Facade::make('hook')->listen('module_init', $request);

        $instance = Loader::controller($controller, $config['url_controller_layer'], $config['controller_suffix'], $config['empty_controller']);
        if (is_null($instance)) {
            throw new HttpException(404, 'controller not exists:' . Loader::parseName($controller, 1));
        }
        // 获取当前操作名
        $action = $actionName . $config['action_suffix'];

        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$actionName];
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        Facade::make('hook')->listen('action_begin', $call);

        return $this->invokeMethod($call, $vars);
    }

    /**
     * 初始化应用
     */
    public function initCommon()
    {
        if (empty($this->init)) {
            // 初始化应用
            $config       = $this->init();
            $this->suffix = $config['class_suffix'];

            // 应用调试模式
            $this->debug = Env::get('app_debug', $this->config('app_debug'));
            if (!$this->debug) {
                ini_set('display_errors', 'Off');
            } elseif (PHP_SAPI != 'cli') {
                //重新申请一块比较大的buffer
                if (ob_get_level() > 0) {
                    $output = ob_get_clean();
                }
                ob_start();
                if (!empty($output)) {
                    echo $output;
                }
            }

            // 注册应用命名空间
            $this->namespace = $config['app_namespace'];
            Loader::addNamespace($config['app_namespace'], APP_PATH);
            if (!empty($config['root_namespace'])) {
                Loader::addNamespace($config['root_namespace']);
            }

            // 加载额外文件
            if (!empty($config['extra_file_list'])) {
                foreach ($config['extra_file_list'] as $file) {
                    $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                    if (is_file($file) && !isset($this->file[$file])) {
                        include $file;
                        $this->file[$file] = true;
                    }
                }
            }

            // 设置系统时区
            date_default_timezone_set($config['default_timezone']);

            // 监听app_init
            Facade::make('hook')->listen('app_init');

            $this->init = true;
        }
        return $this->config();
    }

    /**
     * 初始化应用或模块
     * @access public
     * @param string $module 模块名
     * @return array
     */
    private function init($module = '')
    {
        // 定位模块目录
        $module       = $module ? $module . DS : '';

        // 加载初始化文件
        if (is_file(APP_PATH . $module . 'init' . EXT)) {
            include APP_PATH . $module . 'init' . EXT;
        } elseif (is_file(RUNTIME_PATH . $module . 'init' . EXT)) {
            include RUNTIME_PATH . $module . 'init' . EXT;
        } else {
            $path = APP_PATH . $module;
            // 加载模块配置
            $config = $this->config->load(CONF_PATH . $module . 'config' . CONF_EXT);
            // 读取数据库配置文件
            $filename = CONF_PATH . $module . 'database' . CONF_EXT;
            $this->config->load($filename, 'database');
            // 读取扩展配置文件
            if (is_dir(CONF_PATH . $module . 'extra')) {
                $dir   = CONF_PATH . $module . 'extra';
                $files = scandir($dir);
                foreach ($files as $file) {
                    if (strpos($file, CONF_EXT)) {
                        $filename = $dir . DS . $file;
                        $this->config->load($filename, pathinfo($file, PATHINFO_FILENAME));
                    }
                }
            }

            // 加载应用状态配置
            if ($config['app_status']) {
                $config = $this->config->load(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
            }

            // 加载行为扩展文件
            if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
                Facade::make('hook')->import(include CONF_PATH . $module . 'tags' . EXT);
            }

            // 加载公共文件
            if (is_file($path . 'common' . EXT)) {
                include $path . 'common' . EXT;
            }

            // 加载当前模块语言包
            if ($module) {
                Facade::make('lang')->load($path . 'lang' . DS . Facade::make('request')->langset() . EXT);
            }
        }
        return $this->config();
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @param  \think\Request $request
     * @param  array          $config
     * @return array
     * @throws \think\Exception
     */
    public function routeCheck($request, array $config)
    {
        $path   = $request->path();
        $depr   = $config['pathinfo_depr'];
        $result = false;
        $route  = Facade::make('route');
        // 路由检测
        $check = !is_null($this->routeCheck) ? $this->routeCheck : $config['url_route_on'];
        if ($check) {
            // 开启路由
            if (is_file(RUNTIME_PATH . 'route.php')) {
                // 读取路由缓存
                $rules = include RUNTIME_PATH . 'route.php';
                if (is_array($rules)) {
                    $route->rules($rules);
                }
            } else {
                $files = $config['route_config_file'];
                foreach ($files as $file) {
                    if (is_file(CONF_PATH . $file . CONF_EXT)) {
                        // 导入路由配置
                        $rules = include CONF_PATH . $file . CONF_EXT;
                        if (is_array($rules)) {
                            $route->import($rules);
                        }
                    }
                }
            }

            // 路由检测（根据路由定义返回不同的URL调度）
            $result = $route->check($request, $path, $depr, $config['url_domain_deploy']);
            $must   = !is_null($this->routeMust) ? $this->routeMust : $config['url_route_must'];
            if ($must && false === $result) {
                // 路由无效
                throw new RouteNotFoundException();
            }
        }
        if (false === $result) {
            // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
            $result = $route->parseUrl($path, $depr, $config['controller_auto_search']);
        }
        return $result;
    }

    /**
     * 设置应用的路由检测机制
     * @access public
     * @param  bool $route 是否需要检测路由
     * @param  bool $must  是否强制检测路由
     * @return void
     */
    public function route($route, $must = false)
    {
        $this->routeCheck = $route;
        $this->routeMust  = $must;
    }
}
