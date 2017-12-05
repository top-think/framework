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
namespace think\console\command\optimize;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Container;

class Route extends Command
{
    /** @var  Output */
    protected $output;

    protected function configure()
    {
        $this->setName('optimize:route')
            ->setDescription('Build route cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        file_put_contents(Container::get('app')->getRuntimePath() . 'route.php', $this->buildRouteCache());
        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildRouteCache()
    {
        Container::get('route')->setName([]);
        Container::get('config')->set('url_lazy_route', false);
        // 路由检测
        $path = Container::get('app')->getRoutePath();

        $files = scandir($path);
        if (!empty($files)) {
            foreach ($files as $file) {
                if (strpos($file, '.php')) {
                    $filename = $path . DIRECTORY_SEPARATOR . $file;
                    // 导入路由配置
                    $rules = include $filename;
                    if (is_array($rules)) {
                        Container::get('route')->import($rules);
                    }
                }
            }
        } else {
            $controllers = scandir(Container::get('app')->getAppPath() . 'controller');
            $route       = '';
            foreach ($controllers as $controller) {
                $route .= $this->getControllerRoute($controller);
            }
            $filename = Container::get('app')->getRuntimePath() . '_route.php';
            file_put_contents($filename, $route);
            include $filename;
        }
        $content = '<?php ' . PHP_EOL . 'return ';
        $content .= var_export(Container::get('route')->getName(), true) . ';';
        return $content;
    }

    protected function getControllerRoute($class)
    {
        $class   = new \ReflectionClass($class);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $route   = [];
        foreach ($methods as $method) {
            $route[] = $this->getRouteComment($method);
        }
        return implode('', $route);
    }

    protected function getRouteComment($reflectMethod)
    {
        $comment = substr($reflectMethod->getDocComment(), 3, -2);
        if (strpos($comment, '@route')) {
            $comment = explode("\n", (strstr(trim($comment), '@route')));
            $comment = array_map(function ($item) {return trim(trim($item), '*');}, $comment);
            $key     = array_search('', $comment);
            $comment = implode('', array_slice($comment, 0, $key));
            dump($comment);
        }

    }
}
