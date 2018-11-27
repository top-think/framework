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
declare (strict_types = 1);

namespace think;

use think\exception\ClassNotFoundException;
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * App 应用管理
 */
class App extends Container
{
    const VERSION = '5.2.0beta2';

    /**
     * 应用名称
     * @var string
     */
    protected $name;

    /**
     * 应用入口文件
     * @var string
     */
    protected $scriptName;

    /**
     * 应用调试模式
     * @var bool
     */
    protected $appDebug = true;

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
     * 应用类库顶级命名空间
     * @var string
     */
    protected $rootNamespace = 'app';

    /**
     * 当前应用类库命名空间
     * @var string
     */
    protected $namespace = '';

    /**
     * 应用类库后缀
     * @var bool
     */
    protected $suffix = false;

    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath = '';

    /**
     * 框架目录
     * @var string
     */
    protected $thinkPath = '';

    /**
     * 应用基础目录
     * @var string
     */
    protected $basePath = '';

    /**
     * 应用类库目录
     * @var string
     */
    protected $appPath = '';

    /**
     * 运行时目录
     * @var string
     */
    protected $runtimePath = '';

    /**
     * 配置目录
     * @var string
     */
    protected $configPath = '';

    /**
     * 路由目录
     * @var string
     */
    protected $routePath = '';

    /**
     * 配置后缀
     * @var string
     */
    protected $configExt = '.php';

    /**
     * 初始化
     * @var bool
     */
    protected $initialized = false;

    /**
     * 是否为多应用模式
     * @var bool
     */
    protected $multi = false;

    /**
     * 架构方法
     * @access public
     * @param  string $rootPath 应用根目录
     */
    public function __construct(string $rootPath = '')
    {
        $this->scriptName = $this->getScriptName();
        $this->thinkPath  = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $this->rootPath   = $rootPath ? realpath($rootPath) . DIRECTORY_SEPARATOR : $this->getDefaultRootPath();
        $this->basePath   = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;

        $this->multi = is_dir($this->basePath . 'controller') ? false : true;
    }

    /**
     * 设置应用模式
     * @access public
     * @param  bool $multi
     * @return $this
     */
    public function multi(bool $multi)
    {
        $this->multi = $multi;
        return $this;
    }

    /**
     * 是否为多应用模式
     * @access public
     * @return bool
     */
    public function isMulti(): bool
    {
        return $this->multi;
    }

    /**
     * 设置应用路径
     * @access public
     * @param  string $path 应用目录
     * @return $this
     */
    public function path(string $path)
    {
        $this->appPath = $path;
        return $this;
    }

    /**
     * 开启应用调试模式
     * @access public
     * @param  bool $debug 开启应用调试模式
     * @return $this
     */
    public function debug(bool $debug = true)
    {
        $this->appDebug = $debug;
        return $this;
    }

    /**
     * 设置应用名称
     * @access public
     * @param  string $name 应用名称
     * @return $this
     */
    public function name(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 设置应用命名空间
     * @access public
     * @param  string $namespace 应用命名空间
     * @return $this
     */
    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * 设置是否启用应用类库后缀
     * @access public
     * @param  bool  $suffix 启用应用类库后缀
     * @return $this
     */
    public function suffix(bool $suffix)
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * 初始化应用
     * @access public
     * @return void
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();

        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);

        // 注册错误和异常处理机制
        Error::register();

        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }

        $this->setDependPath();

        $this->configExt = $this->env->get('config_ext', '.php');
        $this->config->set(include $this->rootPath . 'convention.php');

        $this->init();

        if (!$this->suffix) {
            $this->suffix = $this->config['app.class_suffix'];
        }

        $this->debugModeInit();

        if ($this->config['app.exception_handle']) {
            Error::setExceptionHandler($this->config['app.exception_handle']);
        }

        date_default_timezone_set($this->config['app.default_timezone']);

        $this->loadLangPack();

        $this->routeInit();
    }

    protected function debugModeInit(): void
    {
        // 应用调试模式
        if (!$this->appDebug) {
            $this->appDebug = $this->env->get('app_debug', $this->config['app.app_debug']);
        }

        $this->env->set('app_debug', $this->appDebug);

        if (!$this->appDebug) {
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
    }

    protected function setDependPath(): void
    {
        if ($this->multi) {
            $this->name        = $this->name ?: pathinfo($this->scriptName, PATHINFO_FILENAME);
            $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR;
            $this->routePath   = $this->rootPath . 'route' . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR;
        } else {
            $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;
            $this->routePath   = $this->rootPath . 'route' . DIRECTORY_SEPARATOR;
        }

        if (!$this->appPath) {
            $this->appPath = $this->multi ? $this->basePath . $this->name . DIRECTORY_SEPARATOR : $this->basePath;
        }

        $this->configPath = $this->rootPath . 'config' . DIRECTORY_SEPARATOR;

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
    }

    /**
     * 初始化应用
     * @access public
     * @return void
     */
    public function init(): void
    {
        // 加载初始化文件
        if (is_file($this->runtimePath . 'init.php')) {
            include $this->runtimePath . 'init.php';
        } else {
            $this->loadAppFile();
        }

        if ($this->config['app.root_namespace']) {
            $this->rootNamespace = $this->config['app.root_namespace'];
        }

        if (!$this->namespace) {
            if ($this->multi && $this->config['app.app_namespace']) {
                $this->namespace = $this->config['app.app_namespace'];
            } else {
                $this->namespace = $this->multi ? $this->rootNamespace . '\\' . $this->name : $this->rootNamespace;
            }
        }

        $this->env->set('app_namespace', $this->namespace);
        $this->request->setApp($this->name ?: '');
        $this->request->filter($this->config['app.default_filter']);
    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function loadAppFile(): void
    {
        if (is_file($this->appPath . 'event.php')) {
            $event = include $this->appPath . 'event.php';

            if (isset($event['bind'])) {
                $this->event->bind($event['bind']);
            }

            if (isset($event['listen'])) {
                $this->event->listenEvents($event['listen']);
            }

            if (isset($event['subscribe'])) {
                $this->event->subscribe($event['subscribe']);
            }
        }

        if ($this->multi && is_file($this->basePath . 'common.php')) {
            include_once $this->basePath . 'common.php';
        }

        if (is_file($this->appPath . 'common.php')) {
            include_once $this->appPath . 'common.php';
        }

        include $this->thinkPath . 'helper.php';

        if (is_file($this->appPath . 'middleware.php')) {
            $middleware = include $this->appPath . 'middleware.php';
            if (is_array($middleware)) {
                $this->middleware->import($middleware);
            }
        }

        if (is_file($this->appPath . 'provider.php')) {
            $provider = include $this->appPath . 'provider.php';
            if (is_array($provider)) {
                $this->bind($provider);
            }
        }

        $files = [];

        if (is_dir($this->configPath)) {
            $files = glob($this->configPath . '*' . $this->configExt);
        }

        if ($this->multi) {
            if (is_dir($this->appPath . 'config')) {
                $files = array_merge($files, glob($this->appPath . 'config' . DIRECTORY_SEPARATOR . '*' . $this->configExt));
            } elseif (is_dir($this->configPath . $this->name)) {
                $files = array_merge($files, glob($this->configPath . $this->name . DIRECTORY_SEPARATOR . '*' . $this->configExt));
            }
        }

        foreach ($files as $file) {
            $this->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }
    }

    /**
     * 执行应用程序
     * @access public
     * @return Response
     * @throws Exception
     */
    public function run(): Response
    {
        try {
            // 初始化应用
            $this->initialize();

            // 监听AppInit
            $this->event->trigger('AppInit');

            // 路由检测
            $dispatch = $this->routeCheck()->init();

            // 记录当前调度信息
            $this->request->dispatch($dispatch);

            // 记录路由和请求信息
            if ($this->appDebug) {
                $this->log('[ ROUTE ] ' . var_export($this->request->routeInfo(), true));
                $this->log('[ HEADER ] ' . var_export($this->request->header(), true));
                $this->log('[ PARAM ] ' . var_export($this->request->param(), true));
            }

            // 监听AppBegin
            $this->event->trigger('AppBegin');

            // 请求缓存检查
            $this->checkRequestCache();

            $data = null;
        } catch (HttpResponseException $exception) {
            $dispatch = null;
            $data     = $exception->getResponse();
        }

        $this->middleware->add(function (Request $request, $next) use ($dispatch, $data) {
            return is_null($data) ? $dispatch->run() : $data;
        });

        $response = $this->middleware->dispatch($this->request);

        // 监听AppEnd
        $this->event->trigger('AppEnd', $response);

        return $response;
    }

    protected function getRouteCacheKey(): string
    {
        if ($this->config->get('route_check_cache_key')) {
            $closure  = $this->config->get('route_check_cache_key');
            $routeKey = $closure($this->request);
        } else {
            $routeKey = md5($this->request->baseUrl(true) . ':' . $this->request->method());
        }

        return $routeKey;
    }

    protected function loadLangPack(): void
    {
        // 读取默认语言
        $this->lang->range($this->config['app.default_lang']);
        if ($this->config['app.lang_switch_on']) {
            // 开启多语言机制 检测当前语言
            $this->lang->detect();
        }

        $this->request->setLangset($this->lang->range());

        // 加载系统语言包
        $this->lang->load([
            $this->thinkPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
            $this->appPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
        ]);
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $msg  调试信息
     * @param  string $type 信息类型
     * @return void
     */
    public function log($msg, string $type = 'info'): void
    {
        $this->log->record($msg, $type);
    }

    /**
     * 路由初始化（路由规则注册）
     * @access public
     * @return void
     */
    public function routeInit(): void
    {
        // 加载路由定义
        if (is_dir($this->routePath)) {
            $files = glob($this->routePath . DIRECTORY_SEPARATOR . '*.php');
            foreach ($files as $file) {
                include $file;
            }
        }

        if ($this->route->config('route_annotation')) {
            // 自动生成注解路由定义
            if ($this->appDebug) {
                $suffix = $this->route->config('controller_suffix') || $this->route->config('class_suffix');
                $this->build->buildRoute($suffix);
            }

            $filename = $this->runtimePath . 'build_route.php';

            if (is_file($filename)) {
                include $filename;
            }
        }
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @return Dispatch
     */
    public function routeCheck(): Dispatch
    {
        // 检测路由缓存
        if (!$this->appDebug && $this->config->get('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $this->config->get('route_cache_option');

            if ($option && $this->cache->connect($option)->has($routeKey)) {
                return $this->cache->connect($option)->get($routeKey);
            } elseif ($this->cache->has($routeKey)) {
                return $this->cache->get($routeKey);
            }
        }

        $path = $this->request->path();

        // 路由检测 返回一个Dispatch对象
        $dispatch = $this->route->check($path, $this->config['app.url_route_must']);

        if (!empty($routeKey)) {
            try {
                if ($option) {
                    $this->cache->connect($option)->tag('route_cache')->set($routeKey, $dispatch);
                } else {
                    $this->cache->tag('route_cache')->set($routeKey, $dispatch);
                }
            } catch (\Exception $e) {
                // 存在闭包的时候缓存无效
            }
        }

        return $dispatch;
    }

    /**
     * 设置当前地址的请求缓存
     * @access protected
     * @return void
     */
    protected function checkRequestCache(): void
    {
        $cache = $this->request->cache($this->config['app.request_cache'],
            $this->config['app.request_cache_expire'],
            $this->config['app.request_cache_except']);

        if ($cache) {
            $this->setResponseCache($cache);
        }
    }

    public function setResponseCache(array $cache): void
    {
        list($key, $expire, $tag) = $cache;

        if (strtotime($this->request->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $this->request->server('REQUEST_TIME')) {
            // 读取缓存
            $response = Response::create()->code(304);
            throw new HttpResponseException($response);
        } elseif ($this->cache->has($key)) {
            list($content, $header) = $this->cache->get($key);

            $response = Response::create($content)->header($header);
            throw new HttpResponseException($response);
        }
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
    public function controller(string $name, string $layer = 'controller', bool $appendSuffix = false, string $empty = '')
    {
        if (false !== strpos($name, '\\')) {
            $class = $name;
        } else {
            $class = $this->parseClass($layer, $name, $appendSuffix);
        }

        if (class_exists($class)) {
            return $this->make($class);
        } elseif ($empty && class_exists($emptyClass = $this->parseClass($layer, $empty, $appendSuffix))) {
            return $this->make($emptyClass);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
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
    public function action(string $url, $vars = [], string $layer = 'controller', bool $appendSuffix = false)
    {
        $info       = pathinfo($url);
        $action     = $info['basename'];
        $controller = '.' != $info['dirname'] ? $info['dirname'] : $this->request->controller();
        $class      = $this->controller($controller, $layer, $appendSuffix);

        if (is_scalar($vars)) {
            if (strpos($vars, '=')) {
                parse_str($vars, $vars);
            } else {
                $vars = [$vars];
            }
        }

        return $this->invokeMethod([$class, $action . $this->config['action_suffix']], $vars);
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param  string $layer  层名 controller model ...
     * @param  string $name   类名
     * @param  bool   $appendSuffix
     * @return string
     */
    public function parseClass(string $layer, string $name, bool $appendSuffix = false): string
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = self::parseName(array_pop($array), 1) . ($this->suffix || $appendSuffix ? ucfirst($layer) : '');
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . $layer . '\\' . $path . $class;
    }

    /**
     * 获取框架版本
     * @access public
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * 获取应用名称
     * @access public
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?: '';
    }

    /**
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->appDebug;
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 获取应用基础目录
     * @access public
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * 获取当前应用目录
     * @access public
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath(): string
    {
        return $this->runtimePath;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getThinkPath(): string
    {
        return $this->thinkPath;
    }

    /**
     * 获取路由目录
     * @access public
     * @return string
     */
    public function getRoutePath(): string
    {
        return $this->routePath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * 获取配置后缀
     * @access public
     * @return string
     */
    public function getConfigExt(): string
    {
        return $this->configExt;
    }

    /**
     * 获取应用类基础命名空间
     * @access public
     * @return string
     */
    public function getRootNamespace(): string
    {
        return $this->rootNamespace;
    }

    /**
     * 获取应用类库命名空间
     * @access public
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * 是否启用类库后缀
     * @access public
     * @return bool
     */
    public function getSuffix(): bool
    {
        return $this->suffix;
    }

    /**
     * 获取应用开启时间
     * @access public
     * @return float
     */
    public function getBeginTime(): float
    {
        return $this->beginTime;
    }

    /**
     * 获取应用初始内存占用
     * @access public
     * @return integer
     */
    public function getBeginMem(): int
    {
        return $this->beginMem;
    }

    // 获取应用根目录
    public function getDefaultRootPath()
    {
        $path = realpath(dirname($this->scriptName));

        if (!is_file($path . DIRECTORY_SEPARATOR . 'think')) {
            $path = dirname($path);
        }

        return $path . DIRECTORY_SEPARATOR;
    }

    protected function getScriptName()
    {
        return 'cli' == PHP_SAPI ? realpath($_SERVER['argv'][0]) : $_SERVER['SCRIPT_FILENAME'];
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @access public
     * @param  string  $name 字符串
     * @param  integer $type 转换类型
     * @param  bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName(string $name = null, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

    /**
     * 获取类名(不包含命名空间)
     * @access public
     * @param  string|object $class
     * @return string
     */
    public static function classBaseName($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * 创建工厂对象实例
     * @access public
     * @param  string $name         工厂类名
     * @param  string $namespace    默认命名空间
     * @return mixed
     */
    public static function factory(string $name, string $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }
}
