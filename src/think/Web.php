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

use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * Web应用管理类
 * @property Route                 $route
 * @property Config                $config
 * @property Cache                 $cache
 * @property Request               $request
 * @property Env                   $env
 * @property Debug                 $debug
 * @property Event                 $event
 * @property Middleware            $middleware
 * @property Log                   $log
 * @property Lang                  $lang
 * @property Db                    $db
 * @property Cookie                $cookie
 * @property Session               $session
 * @property Url                   $url
 * @property Validate              $validate
 * @property Build                 $build
 * @property \think\route\RuleName $rule_name
 */
class Web extends App
{

    /**
     * 是否多应用模式
     * @var bool
     */
    protected $multi = false;

    /**
     * 是否自动多应用
     * @var bool
     */
    protected $auto = false;

    /**
     * 默认应用名（多应用模式）
     * @var string
     */
    protected $defaultApp = 'index';

    /**
     * 路由目录
     * @var string
     */
    protected $routePath = '';

    /**
     * URL
     * @var string
     */
    protected $urlPath = '';

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
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function load(): void
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

    /**
     * 分析应用（参数）
     * @access protected
     * @return void
     */
    protected function parse(): void
    {
        if (is_file($this->rootPath . '.env')) {
            $this->env->load($this->rootPath . '.env');
        }

        $this->parseAppName();

        $this->parsePath();

        if (!$this->namespace) {
            $this->namespace = $this->multi ? $this->rootNamespace . '\\' . $this->name : $this->rootNamespace;
        }

        $this->configExt = $this->env->get('config_ext', '.php');
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
        $this->map   = $map;

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
     * 设置默认应用（对多应用有效）
     * @access public
     * @param  string $name 应用名
     * @return $this
     */
    public function defaultApp(string $name)
    {
        $this->defaultApp = $name;
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
     * 设置是否启用控制器类库后缀
     * @access public
     * @param  bool $suffix 启用控制器类库后缀
     * @return $this
     */
    public function controllerSuffix(bool $suffix = true)
    {
        $this->controllerSuffix = $suffix;
        return $this;
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
     * 获取控制器层名称
     * @access public
     * @return string
     */
    public function getControllerLayer(): string
    {
        return $this->controllerLayer;
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
     * 实例化访问控制器
     * @access public
     * @param  string $name 资源地址
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

    /**
     * 获取路由缓存Key
     * @access protected
     * @return string
     */
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

    /**
     * 获取自动多应用模式下的实际URL Path
     * @access public
     * @return string
     */
    public function getRealPath(): string
    {
        $path = $this->urlPath;

        if ($path && $this->auto) {
            $path = substr_replace($path, '', 0, strpos($path, '/') ? strpos($path, '/') + 1 : strlen($path));
        }

        return $path;
    }

    /**
     * 分析应用路径
     * @access protected
     * @return void
     */
    protected function parsePath(): void
    {
        if ($this->multi) {
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

        $this->env->set([
            'think_path'   => $this->thinkPath,
            'root_path'    => $this->rootPath,
            'app_path'     => $this->appPath,
            'runtime_path' => $this->runtimePath,
            'route_path'   => $this->routePath,
            'config_path'  => $this->configPath,
        ]);
    }

    /**
     * 分析当前请求的应用名
     * @access protected
     * @return void
     */
    protected function parseAppName(): void
    {
        $this->urlPath = $this->request->path();

        if ($this->auto && $this->urlPath) {
            // 自动多应用识别
            $name = current(explode('/', $this->urlPath));

            if (isset($this->map[$name])) {
                if ($this->map[$name] instanceof \Closure) {
                    call_user_func_array($this->map[$name], [$this]);
                } else {
                    $this->name = $this->map[$name];
                }
            } elseif ($name && false !== array_search($name, $this->map)) {
                throw new HttpException(404, 'app not exists:' . $name);
            } else {
                $this->name = $name ?: $this->defaultApp;
            }
        } elseif ($this->multi) {
            $this->name = $this->name ?: $this->getScriptName();
        }

        $this->request->setApp($this->name ?: '');
    }

    /**
     * 获取当前运行入口名称
     * @access protected
     * @return string
     */
    protected function getScriptName(): string
    {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $file = $_SERVER['SCRIPT_FILENAME'];
        } elseif (isset($_SERVER['argv'][0])) {
            $file = realpath($_SERVER['argv'][0]);
        }

        return isset($file) ? pathinfo($file, PATHINFO_FILENAME) : $this->defaultApp;
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

}
