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

namespace think;

use Opis\Closure\SerializableClosure;
use think\exception\ClassNotFoundException;
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * App 应用管理
 * @property Route $route
 * @property Config $config
 * @property Cache $cache
 * @property Request $request
 * @property Env $env
 * @property Debug $debug
 * @property Event $event
 * @property Middleware $middleware
 * @property Log $log
 * @property Lang $lang
 * @property Db $db
 * @property Cookie $cookie
 * @property Session $session
 * @property Url $url
 * @property Validate $validate
 * @property Build $build
 * @property \think\route\RuleName $rule_name
 */
class App extends Container
{
    const VERSION = '5.2.0RC1';

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
     * 是否为自动多应用模式
     * @var bool
     */
    protected $auto = false;

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 是否需要使用路由
     * @var bool
     */
    protected $withRoute = true;

    /**
     * 访问控制器层名称
     * @var string
     */
    protected $controllerLayer = 'controller';

    /**
     * 是否使用控制器类库后缀
     * @var bool
     */
    protected $controllerSuffix = false;

    /**
     * 空控制器名称
     * @var string
     */
    protected $emptyController = 'Error';

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
     * 自动多应用访问
     * @access public
     * @param  array $map 应用路由映射
     * @return $this
     */
    public function autoMulti(array $map = [])
    {
        $this->multi = true;
        $this->auto  = true;

        if ($this->request->path()) {
            $name = current(explode('/', $this->request->path()));

            if (isset($map[$name]) && $map[$name] instanceof \Closure) {
                $map[$name]($this);
            } elseif ($name) {
                $this->name = $map[$name] ?? $name;
            }
        }

        return $this;
    }

    /**
     * 是否为自动多应用模式
     * @access public
     * @return bool
     */
    public function isAutoMulti(): bool
    {
        return $this->auto;
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
     * 设置是否使用事件机制
     * @access public
     * @param  bool $event
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;
        return $this;
    }

    /**
     * 设置是否使用路由
     * @access public
     * @param  bool $route
     * @return $this
     */
    public function withRoute(bool $route)
    {
        $this->withRoute = $route;
        return $this;
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
     * 是否为调试模式
     * @access public
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->appDebug;
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
     * 设置控制器层名称
     * @access public
     * @param  string $layer 控制器层名称
     * @return $this
     */
    public function controllerLayer(string $layer)
    {
        $this->controllerLayer = $layer;
        return $this;
    }

    /**
     * 设置空控制器名称
     * @access public
     * @param  string $empty 空控制器名称
     * @return $this
     */
    public function emptyController(string $empty)
    {
        $this->emptyController = $empty;
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
     * 设置应用根命名空间
     * @access public
     * @param  string $rootNamespace 应用命名空间
     * @return $this
     */
    public function setRootNamespace(string $rootNamespace)
    {
        $this->rootNamespace = $rootNamespace;
        return $this;
    }

    /**
     * 设置是否启用控制器类库后缀
     * @access public
     * @param  bool  $suffix 启用控制器类库后缀
     * @return $this
     */
    public function controllerSuffix(bool $suffix = true)
    {
        $this->controllerSuffix = $suffix;
        return $this;
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
     * 是否启用控制器类库后缀
     * @access public
     * @return bool
     */
    public function hasControllerSuffix(): bool
    {
        return $this->controllerSuffix;
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

    /**
     * 获取控制器层名称
     * @access public
     * @return string
     */
    public function getControllerLayer(): string
    {
        return $this->controllerLayer;
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

        $this->init();

        $this->debugModeInit();

        if ($this->config->get('app.exception_handle')) {
            Error::setExceptionHandler($this->config->get('app.exception_handle'));
        }

        date_default_timezone_set($this->config->get('app.default_timezone', 'Asia/Shanghai'));

        // 设置开启事件机制
        $this->event->withEvent($this->withEvent);
    }

    protected function debugModeInit(): void
    {
        // 应用调试模式
        if (!$this->appDebug) {
            $this->appDebug = $this->env->get('app_debug', false);
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

        if (!$this->namespace) {
            $this->namespace = $this->multi ? $this->rootNamespace . '\\' . $this->name : $this->rootNamespace;
        }

        $this->env->set('app_namespace', $this->namespace);
        $this->request->setApp($this->name ?: '');
    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function loadAppFile(): void
    {
        if ($this->multi && is_file($this->basePath . 'event.php')) {
            $this->loadEvent(include $this->basePath . 'event.php');
        }

        if (is_file($this->appPath . 'event.php')) {
            $this->loadEvent(include $this->appPath . 'event.php');
        }

        if ($this->multi && is_file($this->basePath . 'common.php')) {
            include_once $this->basePath . 'common.php';
        }

        if (is_file($this->appPath . 'common.php')) {
            include_once $this->appPath . 'common.php';
        }

        include $this->thinkPath . 'helper.php';

        if ($this->multi && is_file($this->basePath . 'middleware.php')) {
            $this->middleware->import(include $this->basePath . 'middleware.php');
        }

        if (is_file($this->appPath . 'middleware.php')) {
            $this->middleware->import(include $this->appPath . 'middleware.php');
        }

        if ($this->multi && is_file($this->basePath . 'provider.php')) {
            $this->bind(include $this->basePath . 'provider.php');
        }

        if (is_file($this->appPath . 'provider.php')) {
            $this->bind(include $this->appPath . 'provider.php');
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

    protected function loadEvent(array $event): void
    {
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

    public function getRealPath()
    {
        $path = $this->request->path();

        if ($path && $this->auto) {
            $path = substr_replace($path, '', 0, strpos($path, '/') ? strpos($path, '/') + 1 : strlen($path));
        }

        return $path;
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

            if ($this->withRoute) {
                $dispatch = $this->routeCheck()->init();
            } else {
                $dispatch = $this->route->url($this->getRealPath())->init();
            }

            // 监听AppBegin
            $this->event->trigger('AppBegin');

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

    /**
     * 实例化访问控制器 格式：控制器名
     * @access public
     * @param  string $name              资源地址
     * @return object
     * @throws ClassNotFoundException
     */
    public function controller(string $name)
    {
        $suffix = $this->controllerSuffix ? 'Controller' : '';
        $class  = $this->parseClass($this->controllerLayer, $name . $suffix);

        if (class_exists($class)) {
            return $this->make($class, [], true);
        } elseif ($this->emptyController && class_exists($emptyClass = $this->parseClass($this->controllerLayer, $this->emptyController . $suffix))) {
            return $this->make($emptyClass, [], true);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param  string $layer  层名 controller model ...
     * @param  string $name   类名
     * @return string
     */
    public function parseClass(string $layer, string $name): string
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = self::parseName(array_pop($array), 1);
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . $layer . '\\' . $path . $class;
    }

    // 获取应用根目录
    public function getDefaultRootPath(): string
    {
        $path = realpath(dirname($this->scriptName));

        if (!is_file($path . DIRECTORY_SEPARATOR . 'think')) {
            $path = dirname($path);
        }

        return $path . DIRECTORY_SEPARATOR;
    }

    /**
     * 路由初始化（路由规则注册）
     * @access protected
     * @return void
     */
    protected function routeInit(): void
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
            if ($this->isDebug()) {
                $this->build->buildRoute();
            }

            $filename = $this->runtimePath . 'build_route.php';

            if (is_file($filename)) {
                include $filename;
            }
        }
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access protected
     * @return Dispatch
     */
    protected function routeCheck(): Dispatch
    {
        // 检测路由缓存
        if (!$this->isDebug() && $this->route->config('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $this->route->config('route_cache_option');

            if ($option && $this->cache->connect($option)->has($routeKey)) {
                return $this->cache->connect($option)->get($routeKey);
            } elseif ($this->cache->has($routeKey)) {
                return $this->cache->get($routeKey);
            }
        }

        $this->routeInit();

        // 路由检测
        $dispatch = $this->route->check($this->getRealPath());

        if (!empty($routeKey)) {
            try {
                if (!empty($option)) {
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

    protected function getRouteCacheKey(): string
    {
        if ($this->route->config('route_check_cache_key')) {
            $closure  = $this->route->config('route_check_cache_key');
            $routeKey = $closure($this->request);
        } else {
            $routeKey = md5($this->request->baseUrl(true) . ':' . $this->request->method());
        }

        return $routeKey;
    }

    protected function getScriptName(): string
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
    public static function classBaseName($class): string
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

    public static function serialize($data): string
    {
        SerializableClosure::enterContext();
        SerializableClosure::wrapClosures($data);
        $data = \serialize($data);
        SerializableClosure::exitContext();
        return $data;
    }

    public static function unserialize(string $data)
    {
        SerializableClosure::enterContext();
        $data = \unserialize($data);
        SerializableClosure::unwrapClosures($data);
        SerializableClosure::exitContext();
        return $data;
    }
}
