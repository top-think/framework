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
use think\console\input\Argument;
use think\console\Output;
use think\Container;
use think\facade\App;

class Route extends Command
{
    protected function configure()
    {
        $this->setName('optimize:route')
            ->addArgument('app', Argument::OPTIONAL, 'Build app route cache .')
            ->setDescription('Build route cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');
        if ($app) {
            $path = App::getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR;
        } else {
            $path = App::getRuntimePath();
        }

        $filename = $path . 'route.php';
        if (is_file($filename)) {
            unlink($filename);
        }
        file_put_contents($filename, $this->buildRouteCache($app));
        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildRouteCache(string $app): string
    {
        Container::pull('route')->setName([]);
        Container::pull('route')->lazy(false);

        // 路由检测
        if ($app) {
            $path = App::getRootPath() . 'route' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR;
        } else {
            $path = App::getRoutePath();
        }

        $files = is_dir($path) ? scandir($path) : [];

        foreach ($files as $file) {
            if (strpos($file, '.php')) {
                include $path . $file;
            }
        }

        if (Container::pull('config')->get('route.route_annotation')) {
            include Container::pull('build')->buildRoute();
        }

        $content = '<?php ' . PHP_EOL . 'return ';
        $content .= '\think\facade\App::unserialize(\'' . \think\facade\App::serialize(Container::pull('route')->getName()) . '\');';
        return $content;
    }

}
