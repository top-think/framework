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
class App extends Container
{
    const VERSION = '5.1.19';

    /**
     * 当前模块路径
     * @var string
     */
    protected $modulePath;

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
     * 应用类库命名空间
     * @var string
     */
    protected $namespace = 'shuguo';

    /**
     * 组织名称
     * @var string
     */
    protected $groupname = 'shuguo';

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
     * 核心模块目录
     * @var string
     */
    protected $corePath;

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
     * 扩展目录
     * @var string
     */
    protected $extendPath;

    /**
     * 第三方库目录
     * @var string
     */
    protected $vendorPath;

    /**
     *  组目录
     */
    protected $groupPath;

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
     * 绑定模块（控制器）
     * @var string
     */
    protected $bindModule;

    /**
     * 模块列表
     */
    protected $moduleList = [];

    /**
     * 应用模块列表
     */
    protected $appModuleList = [];

    /**
     * 组织模块列表
     */
    protected $groupModuleList = [];

    /**
     * 初始化
     * @var bool
     */
    protected $initialized = false;

    public function __construct($appPath = '')
    {
        $this->appPath = $appPath ? realpath($appPath) . DIRECTORY_SEPARATOR : $this->getAppPath();

        $this->thinkPath   = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $this->rootPath    = dirname($this->appPath) . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;
        $this->routePath   = $this->rootPath . 'route' . DIRECTORY_SEPARATOR;
        $this->configPath  = $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
        $this->extendPath  = $this->rootPath . 'extend' . DIRECTORY_SEPARATOR;
        $this->vendorPath  = $this->rootPath . 'vendor' . DIRECTORY_SEPARATOR;
    }

    /**
     * 绑定模块或者控制器
     * @access public
     * @param  string $bind
     * @return $this
     */
    public function bind($bind)
    {
        $this->bindModule = $bind;
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
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $this->beginTime   = microtime(true);
        $this->beginMem    = memory_get_usage();

        static::setInstance($this);

        $this->instance('app', $this);

        // 加载惯例配置文件
        $this->config->set(include $this->thinkPath . 'convention.php');

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

        // 组织名称
        $this->groupname = $this->env->get('app_groupname', $this->groupname);
        $this->env->set('app_groupname', $this->groupname);

        // 设置组织目录
        $this->setGroupPath($this->vendorPath . $this->getGroupname() . DIRECTORY_SEPARATOR);

        // 设置核心目录
        $this->setCorePath($this->groupPath);

        // 设置模块列表
        $this->initModuleList();

        $this->configExt = $this->env->get('config_ext', '.php');

        // 初始化应用
        $this->init();

        // 开启类名后缀
        $this->suffix = $this->config('app.class_suffix');

        // 应用调试模式
        $this->appDebug = $this->env->get('app_debug', $this->config('app.app_debug'));
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

        // 注册异常处理类
        if ($this->config('app.exception_handle')) {
            Error::setExceptionHandler($this->config('app.exception_handle'));
        }

        // 注册根命名空间
        if (!empty($this->config('app.root_namespace'))) {
            Loader::addNamespace($this->config('app.root_namespace'));
        }

        // 加载composer autofile文件
        Loader::loadComposerAutoloadFiles();

        // 注册类库别名
        Loader::addClassAlias($this->config->pull('alias'));

        // 数据库配置初始化
        Db::init($this->config->pull('database'));

        // 设置系统时区
        date_default_timezone_set($this->config('app.default_timezone'));

        // 读取语言包
        $this->loadLangPack();

        // 路由初始化
        $this->routeInit();
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

        if (!is_dir($path)) {
            $path = $this->groupPath . $module . 'src' . DIRECTORY_SEPARATOR;
        }

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
                include_once $path . 'common.php';
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
                    $this->bindTo($provider);
                }
            }

            // 自动读取配置文件
            if (is_dir($path . 'config')) {
                $dir = $path . 'config' . DIRECTORY_SEPARATOR;
            } elseif (is_dir($this->configPath . $module)) {
                $dir = $this->configPath . $module;
            }

            $files = isset($dir) ? scandir($dir) : [];

            foreach ($files as $file) {
                if ('.' . pathinfo($file, PATHINFO_EXTENSION) === $this->configExt) {
                    $filename = rtrim($dir, '\\') . DIRECTORY_SEPARATOR . $file;
                    $this->config->load($filename, pathinfo($file, PATHINFO_FILENAME));
                }
            }
        }

        $this->setModulePath($path);

        if ($module) {
            // 对容器中的对象实例进行配置更新
            $this->containerConfigUpdate($module);
        }
    }

    protected function containerConfigUpdate($module)
    {
        $config = $this->config->get();

        // 注册异常处理类
        if ($config['app']['exception_handle']) {
            Error::setExceptionHandler($config['app']['exception_handle']);
        }

        Db::init($config['database']);
        $this->middleware->setConfig($config['middleware']);
        $this->route->setConfig($config['app']);
        $this->request->init($config['app']);
        $this->cookie->init($config['cookie']);
        $this->view->init($config['template']);
        $this->log->init($config['log']);
        $this->session->setConfig($config['session']);
        $this->debug->setConfig($config['trace']);
        $this->cache->init($config['cache'], true);

        // 加载当前模块语言包
//        $path = $this->appPath . $module . DIRECTORY_SEPARATOR;
//        if (!is_dir($path)) {
//            $path = $this->groupPath . $module . 'src' . DIRECTORY_SEPARATOR;
//        }
//        $this->lang->load($path . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php');

        // 模块请求缓存检查
        $this->checkRequestCache(
            $config['app']['request_cache'],
            $config['app']['request_cache_expire'],
            $config['app']['request_cache_except']
        );
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

            // 监听app_init
            $this->hook->listen('app_init');

            if ($this->bindModule) {
                // 模块/控制器绑定
                $this->route->bind($this->bindModule);
            } elseif ($this->config('app.auto_bind_module')) {
                // 入口自动绑定
                $name = pathinfo($this->request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir($this->appPath . $name)) {
                    $this->route->bind($name);
                }
            }

            // 监听app_dispatch
            $this->hook->listen('app_dispatch');

            $dispatch = $this->dispatch;
            if (empty($dispatch)) {
                // 路由检测
                $dispatch = $this->routeCheck()->init();
                if($dispatch){
                    $this->routeRecord();
                }
            }

            // 记录当前调度信息
            $this->request->dispatch($dispatch);

            // 记录路由和请求信息
            if ($this->appDebug) {
                $this->log('[ ROUTE ] ' . var_export($this->request->routeInfo(), true));
                $this->log('[ HEADER ] ' . var_export($this->request->header(), true));
                $this->log('[ PARAM ] ' . var_export($this->request->param(), true));
            }

            // 监听app_begin
            $this->hook->listen('app_begin');

            // 请求缓存检查
            $this->checkRequestCache(
                $this->config('request_cache'),
                $this->config('request_cache_expire'),
                $this->config('request_cache_except')
            );

            $data = null;
        } catch (HttpResponseException $exception) {
            $dispatch = null;
            $data     = $exception->getResponse();
        }

        $this->middleware->add(function (Request $request, $next) use ($dispatch, $data) {
            return is_null($data) ? $dispatch->run() : $data;
        });

        $response = $this->middleware->dispatch($this->request);

        // 监听app_end
        $this->hook->listen('app_end', $response);

        return $response;
    }

    protected function getRouteCacheKey()
    {
        if ($this->config->get('route_check_cache_key')) {
            $closure  = $this->config->get('route_check_cache_key');
            $routeKey = $closure($this->request);
        } else {
            $routeKey = md5($this->request->baseUrl(true) . ':' . $this->request->method());
        }

        return $routeKey;
    }

    protected function loadLangPack()
    {
        // 读取默认语言
        $this->lang->range($this->config('app.default_lang'));

        if ($this->config('app.lang_switch_on')) {
            // 开启多语言机制 检测当前语言
            $this->lang->detect();
        }

        $this->request->setLangset($this->lang->range());

        // 系统语言包
        $lang = [
            $this->thinkPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
            $this->appPath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
            $this->corePath . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php',
        ];

        // 应用语言包
        foreach ($this->appModuleList as $module) {
            $lang[] = $this->appPath . $module . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php';
        }

        // 组织语言包
        foreach ($this->groupModuleList as $module) {
            $lang[] = $this->groupPath . $module . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $this->request->langset() . '.php';
        }

        // 加载所有语言包
        $this->lang->load($lang);
    }

    /**
     * 设置当前地址的请求缓存
     * @access public
     * @param  string $key 缓存标识，支持变量规则 ，例如 item/:name/:id
     * @param  mixed  $expire 缓存有效期
     * @param  array  $except 缓存排除
     * @param  string $tag    缓存标签
     * @return void
     */
    public function checkRequestCache($key, $expire = null, $except = [], $tag = null)
    {
        $cache = $this->request->cache($key, $expire, $except, $tag);

        if ($cache) {
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
        $this->appDebug && $this->log->record($msg, $type);
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
     * 路由初始化 导入路由定义规则
     * @access public
     * @return void
     */
    public function routeInit()
    {
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

        // 扩展路由检测
        $files = scandir($this->groupPath);
        foreach ($files as $file) {
            if ('.' != $file && '..' != $file) {
                $path = $this->groupPath . $file . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
                if (is_dir($path) && is_file($path . 'module.json')) {
                    $filename = $path . 'route.php';
                    if (is_file($filename)) {
                        // 导入路由配置
                        $rules = include $filename;
                        if (is_array($rules)) {
                            $this->route->import($rules);
                        }
                    }
                }
            }
        }

        if ($this->config('route.route_annotation')) {
            // 自动生成路由定义
            if ($this->appDebug) {
                $this->build->buildRoute($this->config('route.controller_suffix'));
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
    public function routeCheck()
    {
        // 检测路由缓存
        if (!$this->appDebug && $this->config->get('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $this->config->get('route_cache_option') ?: $this->cache->getConfig();

            if ($this->cache->connect($option)->has($routeKey)) {
                return $this->cache->connect($option)->get($routeKey);
            }
        }

        // 获取应用调度信息
        $path = $this->request->path();

        // 是否强制路由模式
        $must = !is_null($this->routeMust) ? $this->routeMust : $this->route->config('url_route_must');
        
        // 路由检测 返回一个Dispatch对象
        $dispatch = $this->route->check($path, $must);

        if (!empty($routeKey)) {
            try {
                $this->cache
                    ->connect($option)
                    ->tag('route_cache')
                    ->set($routeKey, $dispatch);
            } catch (\Exception $e) {
                // 存在闭包的时候缓存无效
            }
        }

        return $dispatch;
    }
    
    /**
     * 记录应用路由信息
     */
    public function routeRecord()
    {
        global $sg;
        
        $config = $this->config();
        if ($config) {
            sgconfig($config);
        }
        
        $module     = $this->request->module();
        $controller = $this->request->controller();
        $action     = $this->request->action();
        if (!isset($module) && !isset($controller) && !isset($action)) {
            $sg['module']     = $this->config('default_module');
            $sg['controller'] = $this->config('default_controller');
            $sg['action']     = $this->config('default_action');
        } else {
            $sg['module']     = isset($module) && !empty($module) ? $module : $this->config('default_module');
            $sg['controller'] = isset($controller) && !empty($controller) ? $controller : $this->config('default_controller');
            $sg['action']     = isset($action) && !empty($action) ? $action : $this->config('default_action');
        }
        //APP的常量定义
        sgdefine('APP_NAME', $sg['module']);
        sgdefine('CONT_NAME', $sg['controller']);
        sgdefine('ACTION_NAME', $sg['action']);
        sgdefine('TRUE_APPNAME', APP_NAME);
        
        //新增一些CODE常量.用于简化判断操作
        sgdefine('MODULE_CODE', $sg['module'] . '/' . $sg['controller']);
        sgdefine('ACTION_CODE', $sg['module'] . '/' . $sg['controller'] . '/' . $sg['action']);
        sgdefine('APP_RUN_PATH', RUNTIME_PATH . '~' . TRUE_APPNAME);
        
        /*  应用配置  */
        //载入应用配置
        if (!in_array(TRUE_APPNAME, $this->config('deny_module_list')) && is_dir($this->getAppPath() . TRUE_APPNAME)) {
            sgdefine('APP_PATH', APPS_PATH . TRUE_APPNAME . DS);
            sgdefine('APP_URL', APPS_URL . DS . TRUE_APPNAME);
        } elseif (!in_array(TRUE_APPNAME, $this->config('deny_module_list')) && is_dir($this->getGroupPath() . TRUE_APPNAME) && is_file($this->getGroupFile(TRUE_APPNAME))) {
            $extFile = json_decode(file_get_contents($this->getGroupFile(TRUE_APPNAME)), true);
            if (TRUE_APPNAME == strtolower($extFile['name'])) {
                sgdefine('APP_PATH', GROUP_PATH . TRUE_APPNAME . DS . 'src' . DS);
                sgdefine('APP_URL', GROUP_URL . DS . TRUE_APPNAME . DS . 'src');
            }
        }
        
        sgdefine('APP_COMMON_PATH', APP_PATH . 'common');
        sgdefine('APP_COMMAND_PATH', APP_PATH . 'command');
        sgdefine('APP_CONFIG_PATH', APP_PATH . 'config');
        sgdefine('APP_LANG_PATH', APP_PATH . 'lang');
        sgdefine('APP_CONT_PATH', APP_PATH . 'controller');
        sgdefine('APP_MODEL_PATH', APP_PATH . 'model');
        sgdefine('APP_LOGIC_PATH', APP_PATH . 'logic');
        sgdefine('APP_SERVICE_PATH', APP_PATH . 'service');
        sgdefine('APP_VALID_PATH', APP_PATH . 'validate');
        
        //定义语言缓存文件路径常量
        sgdefine('LANG_PATH', DATA_PATH . 'lang');
        sgdefine('LANG_URL', DATA_URL . DS . 'lang');
        
        //默认风格包名称
        if (C('theme_name')) {
            sgdefine('THEME_NAME', C('theme_name'));
        } else {
            sgdefine('THEME_NAME', 'stv1');
        }
        
        //默认静态文件、模版文件目录
        sgdefine('THEME_PATH', PUBLIC_PATH . 'theme' . DS);
        sgdefine('THEME_URL', PUBLIC_URL . DS . 'theme');
        sgdefine('THEME_PUBLIC_PATH', THEME_PATH . 'static' . DS);
        sgdefine('THEME_PUBLIC_URL', THEME_URL . DS . 'static');
        sgdefine('APP_PUBLIC_PATH', APP_PATH . 'static' . DS);
        sgdefine('APP_TPL_PATH', APP_PATH . 'view' . DS . 'default' . DS);
        sgdefine('APP_TPL_URL', APP_URL . DS . 'view' . DS . 'default');
        sgdefine('CANVAS_PATH', ROOT_PATH . 'config' . DS . 'canvas' . DS);
        
        sgdefine('OL_MAP_PATH_URL', ADDON_URL . DS . 'maps' . DS . 'openlayer');
        
        /* 临时兼容代码，新方法开发中 */
        $timer = sprintf('%s%s/app/timer', SG_ROOT, SG_STORAGE);
        // 七天更新一次
        if (!file_exists($timer) || (time() - file_get_contents($timer)) > 604800) {
            \shuguo\core\facade\AppInstall::moveAllApplicationResources(); // 移动应用所有的资源
            \Medz\Component\Filesystem\Filesystem::mkdir(dirname($timer), 0777);
            file_put_contents($timer, time());
        }
        sgdefine('APP_PUBLIC_URL', sprintf('%s%s/app/%s', SITE_URL, SG_STORAGE, strtolower(APP_NAME)));
        
        //根据应用配置重定义以下常量
        if (C('app_tpl_path')) {
            sgdefine('APP_TPL_PATH', C('app_tpl_path'));
        }
        
        //如果是部署模式、则如下定义
        if (C('deploy_static')) {
            sgdefine('THEME_PUBLIC_URL', PUBLIC_URL . DS . THEME_NAME);
            sgdefine('APP_PUBLIC_URL', THEME_PUBLIC_URL . DS . TRUE_APPNAME);
        }
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

        return $this->invokeMethod([$class, $action . $this->config('action_suffix')], $vars);
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
        return $this->appDebug;
    }

    /**
     * 初始化模块列表
     */
    public function initModuleList()
    {
        $this->appModuleList = $this->getModuleByDir($this->appPath);
        $this->groupModuleList = $this->getModuleByDir($this->groupPath);
        $this->moduleList = array_merge($this->appModuleList, $this->groupModuleList);
    }

    /**
     * 获取所有模块路径
     * @access public
     * @return string
     */
    public function getModuleList()
    {
        return $this->moduleList;
    }

    /**
     * 获取应用下所有模块
     * @return array
     */
    public function getAppModuleList()
    {
        return $this->appModuleList;
    }

    /**
     * 获取组织下的所有模块
     * @return array
     */
    public function getGroupModuleList()
    {
        return $this->groupModuleList;
    }

    /**
     * 根据目录条件，获取所有模块
     * @param $dir
     * @return array
     */
    public function getModuleByDir($dir)
    {
        $module = [];
        $files = scandir($dir) ? scandir($dir) : [];
        foreach ($files as $file) {
            if ('.' != $file && '..' != $file) {
                $path = '';
                if ($this->appPath !== $dir) {
                    $path = $dir . $file . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
                } else {
                    if (is_dir($dir . $file . DIRECTORY_SEPARATOR)) {
                        $path = $dir . $file . DIRECTORY_SEPARATOR;
                    }
                }

                if (is_dir($path) && is_file($path . 'module.json')) {
                    $modulejson = json_decode(file_get_contents($path . 'module.json'), JSON_FORCE_OBJECT);
                    if ($file == strtolower($modulejson['name'])) {
                        $module[] = $file;
                    }
                }
            }
        }

        return $module;
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
     * 获取扩展目录
     * @access public
     * @return string
     */
    public function getExtendPath()
    {
        return $this->extendPath;
    }

    /**
     * 获取第三方库目录
     * @access public
     * @return string
     */
    public function getVendorPath()
    {
        return $this->vendorPath;
    }

    /**
     * 获取组目录
     * @access public
     * @return string
     */
    public function getGroupPath()
    {
        return $this->groupPath;
    }

    /**
     * 设置组目录
     * @param $path
     */
    public function setGroupPath($path)
    {
        $this->groupPath = $path;
        $this->env->set('group_path', $path);
    }

    /**
     * 设置核心目录
     * @param $path
     */
    public function setCorePath($path)
    {
        $this->corePath = $path . 'core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取组模块文件 module.json
     * @param $module
     * @return string
     */
    public function getGroupFile($module)
    {
        return $this->groupPath . $module . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'module.json';
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
     * 获取组织名称
     * @access public
     * @return string
     */
    public function getGroupname()
    {
        return $this->groupname;
    }

    /**
     * 设置组织名称
     * @access public
     * @param  string $groupname 组织名称
     * @return $this
     */
    public function setGroupname($groupname)
    {
        $this->groupname = $groupname;

        return $this;
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

}
