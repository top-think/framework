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

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\Table;
use think\Container;

class RouteList extends Command
{
    /** @var  Output */
    protected $output;

    protected function configure()
    {
        $this->setName('route:list')
            ->setDescription('show route list.');
    }

    protected function execute(Input $input, Output $output)
    {
        $filename = Container::get('app')->getRuntimePath() . 'route_list.php';

        if (is_file($filename)) {
            unlink($filename);
        }

        $content = $this->getRouteList();
        file_put_contents($filename, 'Route List' . PHP_EOL . $content);
    }

    protected function getRouteList()
    {
        Container::get('route')->setName([]);
        Container::get('route')->lazy(false);
        // 路由检测
        $path = Container::get('app')->getRoutePath();

        $files = is_dir($path) ? scandir($path) : [];

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

        if (Container::get('config')->get('route_annotation')) {
            $suffix = Container::get('config')->get('controller_suffix') || Container::get('config')->get('class_suffix');
            include Container::get('build')->buildRoute($suffix);
        }

        $table = new Table();
        $table->setHeader(['Rule', 'Route', 'Method', 'Name', 'Domain']);

        $routeList = Container::get('route')->getRuleList();
        $rows      = [];

        foreach ($routeList as $domain => $items) {
            foreach ($items as $item) {
                $item['route']  = $item['route'] instanceof \Closure ? 'Closure' : $item['route'];
                $item['domain'] = $domain;

                $rows[] = [$item['rule'], $item['route'], $item['method'], $item['name'], $item['domain']];
            }
        }

        $table->setRows($rows);
        return $this->table($table);
    }

}
