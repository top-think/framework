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
use think\exception\HttpResponseException;
use think\route\Dispatch;

/**
 * Web应用管理类
 */
class Web
{

    /**
     * @var App
     */
    protected $app;


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

    public function __construct(App $app)
    {
        $this->app = $app;
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
     */
    public function run(): Response
    {
        try {
            if ($this->withRoute) {
                $dispatch = $this->routeCheck()->init();
            } else {
                $dispatch = $this->app->route->url($this->getRealPath())->init();
            }

            // 监听AppBegin
            $this->app->event->trigger('AppBegin');

            $data = null;
        } catch (HttpResponseException $exception) {
            $dispatch = null;
            $data     = $exception->getResponse();
        }

        $this->app->middleware->add(function (Request $request, $next) use ($dispatch, $data) {
            return is_null($data) ? $dispatch->run() : $data;
        });

        $response = $this->app->middleware->dispatch($this->app->request);

        // 监听AppEnd
        $this->app->event->trigger('AppEnd', $response);

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
        $class  = $this->app->parseClass($this->controllerLayer, $name . $suffix);

        if (class_exists($class)) {
            return $this->app->make($class, [], true);
        } elseif ($this->emptyController && class_exists($emptyClass = $this->app->parseClass($this->controllerLayer, $this->emptyController . $suffix))) {
            return $this->app->make($emptyClass, [], true);
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
        if (is_dir($this->getRoutePath())) {
            $files = glob($this->getRoutePath() . DIRECTORY_SEPARATOR . '*.php');
            foreach ($files as $file) {
                include $file;
            }
        }

        if ($this->app->route->config('route_annotation')) {
            // 自动生成注解路由定义
            if ($this->app->isDebug()) {
                $this->app->build->buildRoute();
            }

            $filename = $this->app->getRuntimePath() . 'build_route.php';

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
        if (!$this->app->isDebug() && $this->app->route->config('route_check_cache')) {
            $routeKey = $this->getRouteCacheKey();
            $option   = $this->app->route->config('route_cache_option');

            if ($option && $this->app->cache->connect($option)->has($routeKey)) {
                return $this->app->cache->connect($option)->get($routeKey);
            } elseif ($this->app->cache->has($routeKey)) {
                return $this->app->cache->get($routeKey);
            }
        }

        $this->routeInit();

        // 路由检测
        $dispatch = $this->app->route->check($this->getRealPath());

        if (!empty($routeKey)) {
            try {
                if (!empty($option)) {
                    $this->app->cache->connect($option)->tag('route_cache')->set($routeKey, $dispatch);
                } else {
                    $this->app->cache->tag('route_cache')->set($routeKey, $dispatch);
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
        if ($this->app->route->config('route_check_cache_key')) {
            $closure  = $this->app->route->config('route_check_cache_key');
            $routeKey = $closure($this->app->request);
        } else {
            $routeKey = md5($this->app->request->baseUrl(true) . ':' . $this->app->request->method());
        }

        return $routeKey;
    }


    /**
     * 获取自动多应用模式下的实际URL Path
     * @access public
     * @return string
     */
    protected function getRealPath(): string
    {
        $path = $this->app->request->path();

        if ($path && $this->app->isAutoMulti()) {
            $path = substr_replace($path, '', 0, strpos($path, '/') ? strpos($path, '/') + 1 : strlen($path));
        }

        return $path;
    }


    /**
     * 获取路由目录
     * @access public
     * @return string
     */
    public function getRoutePath(): string
    {
        if ($this->app->isMulti()) {
            return $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR . $this->app->getName() . DIRECTORY_SEPARATOR;
        }
        return $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR;
    }


}
