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

use ReflectionClass;
use ReflectionMethod;

class Build
{
    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 应用基础目录
     * @var string
     */
    protected $basePath;

    /**
     * 应用目录
     * @var string
     */
    protected $appPath;

    public function __construct(App $app)
    {
        $this->app      = $app;
        $this->basePath = $app->getBasePath();
        $this->appPath  = $app->getAppPath();
    }

    /**
     * 根据配置文件创建应用和文件
     * @access public
     * @param  array  $config 配置列表
     * @return void
     */
    public function run(array $config): void
    {
        // 创建子目录和文件
        foreach ($config as $app => $list) {
            $this->buildApp(is_numeric($app) ? '' : $app, $list);
        }
    }

    /**
     * 创建应用
     * @access public
     * @param  string $name 应用名
     * @param  array  $list 文件列表
     * @param  string $rootNamespace 应用类库命名空间
     * @return void
     */
    public function buildApp(string $app, array $list = [], string $rootNamespace = 'app'): void
    {
        if (!is_dir($this->basePath . $app)) {
            // 创建应用目录
            mkdir($this->basePath . $app);
        }

        $appPath   = $this->basePath . ($app ? $app . DIRECTORY_SEPARATOR : '');
        $namespace = $rootNamespace . ($app ? '\\' . $app : '');

        // 创建配置文件和公共文件
        $this->buildCommon($app);
        // 创建模块的默认页面
        $this->buildHello($app, $namespace);

        foreach ($list as $path => $file) {
            if ('__dir__' == $path) {
                // 生成子目录
                foreach ($file as $dir) {
                    $this->checkDirBuild($appPath . $dir);
                }
            } elseif ('__file__' == $path) {
                // 生成（空白）文件
                foreach ($file as $name) {
                    if (!is_file($appPath . $name)) {
                        file_put_contents($appPath . $name, 'php' == pathinfo($name, PATHINFO_EXTENSION) ? "<?php\n" : '');
                    }
                }
            } else {
                // 生成相关MVC文件
                foreach ($file as $val) {
                    $val      = trim($val);
                    $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.php';
                    $space    = $namespace . '\\' . $path;
                    $class    = $val;
                    switch ($path) {
                        case 'controller': // 控制器
                            if ($this->app->hasControllerSuffix()) {
                                $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . 'Controller.php';
                                $class    = $val . 'Controller';
                            }
                            $content = "<?php\nnamespace {$space};\n\nuse think\Controller;\n\nclass {$class} extends Controller\n{\n\n}";
                            break;
                        case 'model': // 模型
                            $content = "<?php\nnamespace {$space};\n\nuse think\Model;\n\nclass {$class} extends Model\n{\n\n}";
                            break;
                        case 'view': // 视图
                            $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.html';
                            $this->checkDirBuild(dirname($filename));
                            $content = '';
                            break;
                        default:
                            // 其他文件
                            $content = "<?php\nnamespace {$space};\n\nclass {$class}\n{\n\n}";
                    }

                    if (!is_file($filename)) {
                        file_put_contents($filename, $content);
                    }
                }
            }
        }
    }

    /**
     * 根据注释自动生成路由规则
     * @access public
     * @return string
     */
    public function buildRoute(): string
    {
        $namespace = $this->app->getNameSpace();
        $content   = '<?php ' . PHP_EOL . '//根据 Annotation 自动生成的路由规则';

        $layer  = $this->app->getControllerLayer();
        $suffix = $this->app->hasControllerSuffix();

        $path = $this->appPath . $layer . DIRECTORY_SEPARATOR;
        $content .= $this->buildDirRoute($path, $namespace, $suffix, $layer);

        $filename = $this->app->getRuntimePath() . 'build_route.php';
        file_put_contents($filename, $content);

        return $filename;
    }

    /**
     * 生成子目录控制器类的路由规则
     * @access protected
     * @param  string $path  控制器目录
     * @param  string $namespace 应用命名空间
     * @param  bool   $suffix 类库后缀
     * @param  string $layer 控制器层目录名
     * @return string
     */
    protected function buildDirRoute(string $path, string $namespace, bool $suffix, string $layer): string
    {
        $content     = '';
        $controllers = glob($path . '*.php');

        foreach ($controllers as $controller) {
            $controller = basename($controller, '.php');

            if ($suffix) {
                // 控制器后缀
                $controller = substr($controller, 0, -10);
            }

            $class = new \ReflectionClass($namespace . '\\' . $layer . '\\' . $controller);

            if (strpos($layer, '\\')) {
                // 多级控制器
                $level      = str_replace(DIRECTORY_SEPARATOR, '.', substr($layer, 11));
                $controller = $level . '.' . $controller;
            }

            $content .= $this->getControllerRoute($class, $controller);
        }

        $subDir = glob($path . '*', GLOB_ONLYDIR);

        foreach ($subDir as $dir) {
            $content .= $this->buildDirRoute($dir . DIRECTORY_SEPARATOR, $namespace, $suffix, $layer . '\\' . basename($dir));
        }

        return $content;
    }

    /**
     * 生成控制器类的路由规则
     * @access protected
     * @param  ReflectionClass  $class        控制器反射对象
     * @param  string           $controller   控制器名
     * @return string
     */
    protected function getControllerRoute(ReflectionClass $class, string $controller): string
    {
        $content = '';
        $comment = $class->getDocComment();

        if (false !== strpos($comment, '@route(')) {
            $comment = $this->parseRouteComment($comment);
            $comment = preg_replace('/route\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\)/is', 'Route::resource(\1,\'' . $controller . '\')', $comment);
            $content .= PHP_EOL . $comment;
        } elseif (false !== strpos($comment, '@alias(')) {
            $comment = $this->parseRouteComment($comment, '@alias(');
            $comment = preg_replace('/alias\(\s?([\'\"][\-\_\/\w]+[\'\"])\s?\)/is', 'Route::alias(\1,\'' . $controller . '\')', $comment);
            $content .= PHP_EOL . $comment;
        }

        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $comment = $this->getMethodRouteComment($controller, $method);
            if ($comment) {
                $content .= PHP_EOL . $comment;
            }
        }

        return $content;
    }

    /**
     * 解析路由注释
     * @access protected
     * @param  string $comment
     * @param  string $tag
     * @return string
     */
    protected function parseRouteComment(string $comment, string $tag = '@route('): string
    {
        $comment = substr($comment, 3, -2);
        $comment = explode(PHP_EOL, substr(strstr(trim($comment), $tag), 1));
        $comment = array_map(function ($item) {return trim(trim($item), ' \t*');}, $comment);

        if (count($comment) > 1) {
            $key     = array_search('', $comment);
            $comment = array_slice($comment, 0, false === $key ? 1 : $key);
        }

        $comment = implode(PHP_EOL . "\t", $comment) . ';';

        if (strpos($comment, '{')) {
            $comment = preg_replace_callback('/\{\s?.*?\s?\}/s', function ($matches) {
                return false !== strpos($matches[0], '"') ? '[' . substr(var_export(json_decode($matches[0], true), true), 7, -1) . ']' : $matches[0];
            }, $comment);
        }
        return $comment;
    }

    /**
     * 获取方法的路由注释
     * @access protected
     * @param  string             $controller 控制器名
     * @param  ReflectionMethod   $reflectMethod
     * @return string|void
     */
    protected function getMethodRouteComment(string $controller, ReflectionMethod $reflectMethod)
    {
        $comment = $reflectMethod->getDocComment();

        if (false !== strpos($comment, '@route(')) {
            $comment = $this->parseRouteComment($comment);
            $action  = $reflectMethod->getName();

            if ($suffix = $this->app['route']->config('action_suffix')) {
                $action = substr($action, 0, -strlen($suffix));
            }

            $route   = $controller . '/' . $action;
            $comment = preg_replace('/route\s?\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\,?\s?[\'\"]?(\w+?)[\'\"]?\s?\)/is', 'Route::\2(\1,\'' . $route . '\')', $comment);
            $comment = preg_replace('/route\s?\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\)/is', 'Route::rule(\1,\'' . $route . '\')', $comment);

            return $comment;
        }
    }

    /**
     * 创建应用的欢迎页面
     * @access protected
     * @param  string $appName 应用名
     * @param  string $namespace 应用类库命名空间
     * @return void
     */
    protected function buildHello(string $appName, string $namespace): void
    {
        $suffix   = $this->app->hasControllerSuffix() ? 'Controller' : '';
        $filename = $this->basePath . ($appName ? $appName . DIRECTORY_SEPARATOR : '') . 'controller' . DIRECTORY_SEPARATOR . 'Index' . $suffix . '.php';
        if (!is_file($filename)) {
            $content = file_get_contents($this->app->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'default_index.tpl');
            $content = str_replace(['{$app}', '{layer}', '{$suffix}'], [$namespace, 'controller', $suffix], $content);
            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * 创建应用的公共文件
     * @access protected
     * @param  string $appName 应用名称
     * @return void
     */
    protected function buildCommon(string $appName): void
    {
        $filename = $this->basePath . ($appName ? $appName . DIRECTORY_SEPARATOR : '') . 'common.php';

        if (!is_file($filename)) {
            file_put_contents($filename, "<?php\n");
        }
    }

    /**
     * 创建目录
     * @access protected
     * @param  string $dirname 目录名称
     * @return void
     */
    protected function checkDirBuild(string $dirname): void
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
    }
}
