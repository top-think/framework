<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\ClassNotFoundException;
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * App 应用管理
 */
class App implements \ArrayAccess
{
    const VERSION = '5.1.13';

    /**
     * 当前模块路径
     * @var string
     */
    protected $modulePath;

    /**
     * 应用调试模式
     * @var bool
     */
    protected $debug = true;

    /**
     * 应用开始时间
     * @var float
     */
    protected $beginTime;

    /**
     * 应用内存初始占用
     * @var integer
     */
    protected $beginMem;

    /**
     * 应用类库命名空间
     * @var string
     */
    protected $namespace = 'app';

    /**
     * 应用类库后缀
     * @var bool
     */
    protected $suffix = false;

    /**
     * 严格路由检测
     * @var bool
     */
    protected $routeMust;

    /**
     * 应用类库目录
     * @var string
     */
    protected $appPath;

    /**
     * 框架目录
     * @var string
     */
    protected $thinkPath;

    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath;

    /**
     * 运行时目录
     * @var string
     */
    protected $runtimePath;

    /**
     * 配置目录
     * @var string
     */
    protected $configPath;

    /**
     * 路由目录
     * @var string
     */
    protected $routePath;

    /**
     * 配置后缀
     * @var string
     */
    protected $configExt;

    /**
     * 应用调度实例
     * @var Dispatch
     */
    protected $dispatch;

    /**
     * 容器对象实例
     * @var Container
     */
    protected $container;

    /**
     * 绑定模块（控制器）
     * @var string
     */
    protected $bind;

    public function __construct($appPath = '')
    {
        $this->appPath   = $appPath ? realpath($appPath) . DIRECTORY_SEPARATOR : $this->getAppPath();
        $this->container = Container::getInstance();

        $this->thinkPath   = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $this->rootPath    = dirname($this->appPath) . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;
        $this->routePath   = $this->rootPath . 'route' . DIRECTORY_SEPARATOR;
        $this->configPath  = $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
    }

    /**
     * 绑定模块或者控制器
     * @access public
     * @param  string $bind
     * @return $this
     */
    public function bind($bind)
    {
        $this->bind = $bind;
        return $this;
    }

    /**
     * 设置应用类库目录
     * @access public
     * @param  string $path 路径
     * @return $this
     */
    public function path($path)
    {
        $this->appPath = $path;
        return $this;
    }

    /**
     * 初始化应用
     * @access public
     * @return void
     */
    public function initialize()
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();

        // 设置路径环境变量
        $this->env->set([
            'think_path'   => $this->thinkPath,
            'root_path'    => $this->rootPath,
            'app_path'     => $this->appPath,
            'config_path'  => $this->configPath,
            'route_path'   => $this->routePath,
            'runtime_path' => $this->runtimePath,
            'extend_path'  => $this->rootPath . 'extend' . DIRECTORY_SEPARATOR,
            'vendor_path'  => $this->rootPath . 'vendor' . DIRECTORY_SEPARATOR,
        ]);

        // 加载环境变量配置文件
        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }

        $this->namespace = $this->env->get('app_namespace', $this->namespace);
        $this->env->set('app_namespace', $this->namespace);

        // 注册应用命名空间
        Loader::addNamespace($this->namespace, $this->appPath);

        $this->configExt = $this->env->get('config_ext', '.php');

        // 初始化应用
        $this->init();

        // 开启类名后缀
        $this->suffix = $this->config('app.class_suffix');

        // 应用调试模式
        $this->debug = $this->env->get('app_debug', $this->config('app.app_debug'));
        $this->env->set('app_debug', $this->debug);

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

        // 注册根命名空间
        if (!empty($this->config('app.root_namespace'))) {
            Loader::addNamespace($this->config('app.root_namespace'));
        }

        // 注册类库别名
        Loader::addClassAlias($this->config->pull('alias'));

        // 设置系统时区
        date_default_timezone_set($this->config('app.default_timezone'));

        // 读取语言包
        $this->loadLangPack();

        // 监听app_init
        $this->hook->listen('app_init');
    }

    /**
     * 初始化应用或模块
     * @access public
     * @param  string $module 模块名
     * @return void
     */
    public function init($module = '')
    {
        // 定位模块目录
        $module = $module ? $module . DIRECTORY_SEPARATOR : '';
        $path   = $this->appPath . $module;

        // 加载初始化文件
        if (is_file($path . 'init.php')) {
            include $path . 'init.php';
        } elseif (is_file($this->runtimePath . $module . 'init.php')) {
            include $this->runtimePath . $module . 'init.php';
        } else {
            // 加载行为扩展文件
            if (is_file($path . 'tags.php')) {
                $tags = include $path . 'tags.php';
                if (is_array($tags)) {
                    $this->hook->import($tags);
                }
            }

            // 加载公共文件
            if (is_file($path . 'common.php')) {
                include $path . 'common.php';
            }

            if ('' == $module) {
                // 加载系统助手函数
                include $this->thinkPath . 'helper.php';
            }

            // 加载中间件
            if (is_file($path . 'middleware.php')) {
                $middleware = include $path . 'middleware.php';
                if (is_array($middleware)) {
                    $this->middleware->import($middleware);
                }
            }

            // 注册服务的容器对象实例
            if (is_file($path . 'provider.php')) {
                $provider = include $path . 'provider.php';
                if (is_array($provider)) {
                    $this->container->bind($provider);
                }
            }

            // 自动读取配置文件
            if (is_dir($path . 'config')) {
                $dir = $path . 'config';
            } elseif (is_dir($this->configPath . $module)) {
                $dir = $this->configPath . $module;
            }

            $files = isset($dir) ? scandir($dir) : [];

            foreach ($files as $file) {
                if ('.' . pathinfo($file, PATHINFO_EXTENSION) === $this->configExt) {
                    $filename = $dir . DIRECTORY_SEPARATOR . $file;
                    $this->config->load($filename, pathinfo($file, PATHINFO_FILENAME));
                }
            }
        }

        $this->setModulePath($path);

        $this->request->filter($this->config('app.default_filter'));
    }

    /**
     * 执行应用程序
     * @access public
     * @return Response
     * @throws Exception
     */
    public function run()
    {
        try {
            // 初始化应用
            $this->initialize();

            if ($this->bind) {
                // 模块/控制器绑定
                $this->route->bind($this->bind);
            } elseif ($this->config('app.auto_bind_module')) {
                // 入口自动绑定
                $name = pathinfo($this->request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir($this->appPath . $name)) {
                    $this->route->bind($name);
                }
            }

            // 监听app_dispatch
            $this->hook->listen('app_dispatch');

            // 获取应用调度信息
            $dispatch = $this->dispatch;
            if (empty($dispatch)) {
                // 路由检测
                $this->route
                    ->lazy($this->config('app.url_lazy_route'))
                    ->autoSearchController($this->config('app.controller_auto_search'))
                    ->mergeRuleRegex($this->config('app.route_rule_merge'));

                $dispatch = $this->routeCheck();
            }

            // 记录当前调度信息
            $this->request->dispatch($dispatch);

            // 记录路由和请求信息
            if ($this->debug) {
                $this->log('[ ROUTE ] ' . var_export($this->request->routeInfo(), true));
                $this->log('[ HEADER ] ' . var_export($this->request->header(), true));
                $this->log('[ PARAM ] ' . var_export($this->request->param(), true));
            }

            // 监听app_begin
            $this->hook->listen('app_begin');

            // 请求缓存检查
            $this->request->cache(
                $this->config('app.request_cache'),
                $this->config('app.request_cache_expire'),
                $this->config('app.request_cache_except')
            );

            $data = null;
        } catch (HttpResponseException $exception) {
            $dispatch = null;
            $data     = $exception->getResponse();
        }

        $this->middleware->add(function (Request $request, $next) use ($dispatch, $data) {
            if (is_null($data)) {
                try {
                    // 执行调度
                    $data = $dispatch->run();
                } catch (HttpResponseException $exception) {
                    $data = $exception->getResponse();
                }
            }

            // 输出数据到客户端
            if ($data instanceof Response) {
                $response = $data;
            } elseif (!is_null($data)) {
                // 默认自动识别响应输出类型
                $isAjax = $request->isAjax();
                $type   = $isAjax ? $this->config('app.default_ajax_return') : $this->config('app.default_return_type');

                $response = Response::create($data, $type);
            } else {
                $response = Response::create();
            }
            return $response;
        });

        $response = $this->middleware->dispatch($this->request);

        // 监听app_end
        $this->hook->listen('app_end', $response);

        return $response;
    }

    protected function loadLangPack()
    {
        // 读取默认语言
        $this->lang->range($this->config('app.default_lang'));
        if ($this->config('app.lang_switch_on')) {
            // 开启多语言机制 检测当前语言
            $this->lang->detect();
        }

        $this->request->langset($this->lang->range());

        // 加载系统语言包
        $this->lang->load([
            $this->thinkPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
            $this->appPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
        ]);
    }

    /**
     * 设置当前请求的调度信息
     * @access public
     * @param  Dispatch  $dispatch 调度信息
     * @return $this
     */
    public function dispatch(Dispatch $dispatch)
    {
        $this->dispatch = $dispatch;
        return $this;
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $msg  调试信息
     * @param  string $type 信息类型
     * @return void
     */
    public function log($msg, $type = 'info')
    {
        $this->debug && $this->log->record($msg, $type);
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string    $name 配置参数名（支持二级配置 .号分割）
     * @return mixed
     */
    public function config($name = '')
    {
        return $this->config->get($name);
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @return Dispatch
     */
    public function routeCheck()
    {
        $path = $this->request->path();
        $depr = $this->config('app.pathinfo_depr');

        // 路由检测
        $files = scandir($this->routePath);
        foreach ($files as $file) {
            if (strpos($file, '.php')) {
                $filename = $this->routePath . $file;
                // 导入路由配置
                $rules = include $filename;
                if (is_array($rules)) {
                    $this->route->import($rules);
                }
            }
        }

        if ($this->config('app.route_annotation')) {
            // 自动生成路由定义
            if ($this->debug) {
                $this->build->buildRoute($this->config('app.controller_suffix'));
            }

            $filename = $this->runtimePath . 'build_route.php';

            if (is_file($filename)) {
                include $filename;
            }
        }

        if (is_file($this->runtimePath . 'rule_regex.php')) {
            $this->route->setRuleRegexs(include $this->runtimePath . 'rule_regex.php');
        }

        // 是否强制路由模式
        $must = !is_null($this->routeMust) ? $this->routeMust : $this->config('app.url_route_must');

        // 路由检测 返回一个Dispatch对象
        return $this->route->check($path, $depr, $must, $this->config('app.route_complete_match'));
    }

    /**
     * 设置应用的路由检测机制
     * @access public
     * @param  bool $must  是否强制检测路由
     * @return $this
     */
    public function routeMust($must = false)
    {
        $this->routeMust = $must;
        return $this;
    }

    /**
     * 解析模块和类名
     * @access protected
     * @param  string $name         资源地址
     * @param  string $layer        验证层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @return array
     */
    protected function parseModuleAndClass($name, $layer, $appendSuffix)
    {
        if (false !== strpos($name, '\\')) {
            $class  = $name;
            $module = $this->request->module();
        } else {
            if (strpos($name, '/')) {
                list($module, $name) = explode('/', $name, 2);
            } else {
                $module = $this->request->module();
            }

            $class = $this->parseClass($module, $layer, $name, $appendSuffix);
        }

        return [$module, $class];
    }

    /**
     * 实例化应用类库
     * @access public
     * @param  string $name         类名称
     * @param  string $layer        业务层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @param  string $common       公共模块名
     * @return object
     * @throws ClassNotFoundException
     */
    public function create($name, $layer, $appendSuffix = false, $common = 'common')
    {
        $guid = $name . $layer;

        if ($this->__isset($guid)) {
            return $this->__get($guid);
        }

        list($module, $class) = $this->parseModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            $object = $this->__get($class);
        } else {
            $class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
            if (class_exists($class)) {
                $object = $this->__get($class);
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }
        }

        $this->__set($guid, $class);

        return $object;
    }

    /**
     * 实例化（分层）模型
     * @access public
     * @param  string $name         Model名称
     * @param  string $layer        业务层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @param  string $common       公共模块名
     * @return Model
     * @throws ClassNotFoundException
     */
    public function model($name = '', $layer = 'model', $appendSuffix = false, $common = 'common')
    {
        return $this->create($name, $layer, $appendSuffix, $common);
    }

    /**
     * 实例化（分层）控制器 格式：[模块名/]控制器名
     * @access public
     * @param  string $name              资源地址
     * @param  string $layer             控制层名称
     * @param  bool   $appendSuffix      是否添加类名后缀
     * @param  string $empty             空控制器名称
     * @return object
     * @throws ClassNotFoundException
     */
    public function controller($name, $layer = 'controller', $appendSuffix = false, $empty = '')
    {
        list($module, $class) = $this->parseModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            return $this->__get($class);
        } elseif ($empty && class_exists($emptyClass = $this->parseClass($module, $layer, $empty, $appendSuffix))) {
            return $this->__get($emptyClass);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 实例化验证类 格式：[模块名/]验证器名
     * @access public
     * @param  string $name         资源地址
     * @param  string $layer        验证层名称
     * @param  bool   $appendSuffix 是否添加类名后缀
     * @param  string $common       公共模块名
     * @return Validate
     * @throws ClassNotFoundException
     */
    public function validate($name = '', $layer = 'validate', $appendSuffix = false, $common = 'common')
    {
        $name = $name ?: $this->config('default_validate');

        if (empty($name)) {
            return new Validate;
        }

        return $this->create($name, $layer, $appendSuffix, $common);
    }

    /**
     * 数据库初始化
     * @access public
     * @param  mixed         $config 数据库配置
     * @param  bool|string   $name 连接标识 true 强制重新连接
     * @return \think\db\Query
     */
    public function db($config = [], $name = false)
    {
        return Db::connect($config, $name);
    }

    /**
     * 远程调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @access public
     * @param  string       $url          调用地址
     * @param  string|array $vars         调用参数 支持字符串和数组
     * @param  string       $layer        要调用的控制层名称
     * @param  bool         $appendSuffix 是否添加类名后缀
     * @return mixed
     * @throws ClassNotFoundException
     */
    public function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        $info   = pathinfo($url);
        $action = $info['basename'];
        $module = '.' != $info['dirname'] ? $info['dirname'] : $this->request->controller();
        $class  = $this->controller($module, $layer, $appendSuffix);

        if (is_scalar($vars)) {
            if (strpos($vars, '=')) {
                parse_str($vars, $vars);
            } else {
                $vars = [$vars];
            }
        }

        return $this->container->invokeMethod([$class, $action . $this->config('action_suffix')], $vars);
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param  string $module 模块名
     * @param  string $layer  层名 controller model ...
     * @param  string $name   类名
     * @param  bool   $appendSuffix
     * @return string
     */
    public function parseClass($module, $layer, $name, $appendSuffix = false)
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = Loader::parseName(array_pop($array), 1) . ($this->suffix || $appendSuffix ? ucfirst($layer) : '');
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . ($module ? $module . '\\' : '') . $layer . '\\' . $path . $class;
    }

    /**
     * 获取框架版本
     * @access public
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * 获取模块路径
     * @access public
     * @return string
     */
    public function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * 设置模块路径
     * @access public
     * @param  string $path 路径
     * @return void
     */
    public function setModulePath($path)
    {
        $this->modulePath = $path;
        $this->env->set('module_path', $path);
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * 获取应用类库目录
     * @access public
     * @return string
     */
    public function getAppPath()
    {
        if (is_null($this->appPath)) {
            $this->appPath = Loader::getRootPath() . 'application' . DIRECTORY_SEPARATOR;
        }

        return $this->appPath;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath()
    {
        return $this->runtimePath;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getThinkPath()
    {
        return $this->thinkPath;
    }

    /**
     * 获取路由目录
     * @access public
     * @return string
     */
    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * 获取配置后缀
     * @access public
     * @return string
     */
    public function getConfigExt()
    {
        return $this->configExt;
    }

    /**
     * 获取应用类库命名空间
     * @access public
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * 设置应用类库命名空间
     * @access public
     * @param  string $namespace 命名空间名称
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * 是否启用类库后缀
     * @access public
     * @return bool
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * 获取应用开启时间
     * @access public
     * @return float
     */
    public function getBeginTime()
    {
        return $this->beginTime;
    }

    /**
     * 获取应用初始内存占用
     * @access public
     * @return integer
     */
    public function getBeginMem()
    {
        return $this->beginMem;
    }

    /**
     * 获取容器实例
     * @access public
     * @return Container
     */
    public function container()
    {
        return $this->container;
    }

    public function __set($name, $value)
    {
        $this->container->bind($name, $value);
    }

    public function __get($name)
    {
        return $this->container->make($name);
    }

    public function __isset($name)
    {
        return $this->container->bound($name);
    }

    public function __unset($name)
    {
        $this->container->delete($name);
    }

    public function offsetExists($key)
    {
        return $this->__isset($key);
    }

    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->__set($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->__unset($key);
    }
}
