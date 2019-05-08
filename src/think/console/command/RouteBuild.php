<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\console\command;

use ReflectionClass;
use ReflectionMethod;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class RouteBuild extends Command
{
    protected function configure()
    {
        $this->setName('route:build')
            ->addArgument('app', Argument::OPTIONAL, 'Build app route cache .')
            ->setDescription('Build Annotation route rule.');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');

        if (empty($app) && !is_dir($this->app->getBasePath() . 'controller')) {
            $output->writeln('<error>Miss app name!</error>');
            return false;
        }

        $this->buildRoute($app);
        $output->writeln('<info>Succeed!</info>');
    }

    /**
     * 根据注释自动生成路由规则
     * @access protected
     * @return string
     */
    protected function buildRoute(string $app = null): string
    {
        if ($app) {
            $path      = $this->app->getBasePath() . $app . DIRECTORY_SEPARATOR;
            $namespace = 'app\\' . $app;
        } else {
            $path      = $this->app->getBasePath();
            $namespace = 'app';
        }

        $content = '<?php ' . PHP_EOL . '// 根据注解自动生成的路由规则' . PHP_EOL . 'use think\\facade\\Route;' . PHP_EOL;

        $layer  = $this->app->config->get('route.controller_layer');
        $suffix = $this->app->config->get('route.controller_suffix');

        $content .= $this->buildDirRoute($path . $layer . DIRECTORY_SEPARATOR, $namespace, $suffix, $layer);

        $filename = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . ($app ? $app . DIRECTORY_SEPARATOR : '') . 'build_route.php';
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
        $comment = $class->getDocComment() ?: '';

        if (false !== strpos($comment, '@route(')) {
            $comment = $this->parseRouteComment($comment);
            $comment = preg_replace('/route\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\)/is', 'Route::resource(\1,\'' . $controller . '\')', $comment);
            $content .= PHP_EOL . $comment;
        } elseif (false !== strpos($comment, '@group(')) {
            $comment = $this->parseRouteComment($comment, '@group(');
            $comment = preg_replace('/group\(\s?([\'\"][\-\_\/\w]+[\'\"])\s?\)/is', 'Route::group(\1)', $comment);
            $content .= PHP_EOL . $comment;
            preg_match('/group\(\s?[\'\"]([\-\_\/\w]+)[\'\"]\s?\)/is', $comment, $matches);
            $group = $matches[1];
        }

        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $comment = $this->getMethodRouteComment($controller, $method, $group ?? null);
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
     * @param  string           $controller 控制器名
     * @param  ReflectionMethod $reflectMethod
     * @param  string           $group
     * @return string|void
     */
    protected function getMethodRouteComment(string $controller, ReflectionMethod $reflectMethod, string $group = null)
    {
        $comment = $reflectMethod->getDocComment() ?: '';

        if (false !== strpos($comment, '@route(')) {
            $comment = $this->parseRouteComment($comment);
            $action  = $reflectMethod->getName();
            $group   = $group ? '->group(\'' . $group . '\')' : '';

            if ($suffix = $this->app->route->config('action_suffix')) {
                $action = substr($action, 0, -strlen($suffix));
            }

            $route   = $controller . '/' . $action;
            $comment = preg_replace('/route\s?\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\,?\s?[\'\"]?(\w+?)[\'\"]?\s?\)/is', 'Route::\2(\1,\'' . $route . '\')' . $group, $comment);
            $comment = preg_replace('/route\s?\(\s?([\'\"][\-\_\/\:\<\>\?\$\[\]\w]+[\'\"])\s?\)/is', 'Route::rule(\1,\'' . $route . '\')' . $group, $comment);

            return $comment;
        }
    }
}
