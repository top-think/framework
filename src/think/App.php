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

/**
 * App 基础类
 */
abstract class App extends Container
{
    const VERSION = '5.2.0RC1';

    /**
     * 应用名称
     * @var string
     */
    protected $name;

    /**
     * 应用调试模式
     * @var bool
     */
    protected $appDebug = true;

    /**
     * 应用映射
     * @var array
     */
    protected $map = [];

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
     * 配置后缀
     * @var string
     */
    protected $configExt = '.php';

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 注册的系统服务
     * @var array
     */
    protected static $servicer = [
        Error::class,
    ];

    /**
     * 架构方法
     * @access public
     * @param  string $rootPath 应用根目录
     */
    public function __construct(string $rootPath = '')
    {
        $this->thinkPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $this->rootPath  = $rootPath ? realpath($rootPath) . DIRECTORY_SEPARATOR : $this->getDefaultRootPath();
        $this->basePath  = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;

        static::setInstance($this);

        $this->instance('app', $this);

        // 注册系统服务
        foreach (self::$servicer as $servicer) {
            $this->make($servicer)->register($this);
        }
    }

    /**
     * 注册一个系统服务
     * @access public
     * @param  string $servicer
     * @return void
     */
    public static function service(string $servicer): void
    {
        self::$servicer[] = $servicer;
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
     * 初始化应用
     * @access public
     * @return $this
     */
    public function initialize()
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();

        $this->parse();

        $this->init();

        return $this;
    }

    /**
     * 分析应用（参数）
     * @access protected
     * @return void
     */
    abstract protected function parse(): void;

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
            $this->load();
        }

        // 设置开启事件机制
        $this->event->withEvent($this->withEvent);

        // 监听AppInit
        $this->event->trigger('AppInit');

        $this->debugModeInit();

        date_default_timezone_set($this->config->get('app.default_timezone', 'Asia/Shanghai'));
    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function load(): void
    {
        if (is_file($this->appPath . 'event.php')) {
            $this->loadEvent(include $this->appPath . 'event.php');
        }

        if (is_file($this->appPath . 'common.php')) {
            include_once $this->appPath . 'common.php';
        }

        include $this->thinkPath . 'helper.php';

        if (is_file($this->appPath . 'middleware.php')) {
            $this->middleware->import(include $this->appPath . 'middleware.php');
        }

        if (is_file($this->appPath . 'provider.php')) {
            $this->bind(include $this->appPath . 'provider.php');
        }

        $files = [];

        if (is_dir($this->configPath)) {
            $files = glob($this->configPath . '*' . $this->configExt);
        }

        foreach ($files as $file) {
            $this->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }
    }

    /**
     * 调试模式设置
     * @access protected
     * @return void
     */
    protected function debugModeInit(): void
    {
        // 应用调试模式
        if (!$this->appDebug) {
            $this->appDebug = $this->env->get('app_debug', false);
        }

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

    /**
     * 注册应用事件
     * @access protected
     * @param array $event
     * @return void
     */
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

    abstract public function run();

    /**
     * 解析应用类的类名
     * @access public
     * @param  string $layer 层名 controller model ...
     * @param  string $name  类名
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

    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        $path = dirname(dirname(dirname(dirname($this->thinkPath))));

        return $path . DIRECTORY_SEPARATOR;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @access public
     * @param  string  $name    字符串
     * @param  integer $type    转换类型
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
     * @param  string $name      工厂类名
     * @param  string $namespace 默认命名空间
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
