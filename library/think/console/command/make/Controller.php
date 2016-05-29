<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace think\console\command\make;

use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Controller extends \think\console\command\Make
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('make:controller')
            ->setDescription('Create a new controller class')
            ->addArgument('namespace', Argument::OPTIONAL, null)
            ->addOption('module', 'm', Option::VALUE_OPTIONAL, 'Module Name', null)
            ->addOption('extend', 'e', Option::VALUE_OPTIONAL, 'Base on Controller class', null);
    }

    protected function execute(Input $input, Output $output)
    {
        $namespace = $input->getArgument('namespace');
        $module = $input->getOption('module');


        // 处理命名空间
        if (!empty($module)) {
            $namespace = APP_NAMESPACE . "\\" . $module . "\\" . 'controller' . "\\" . $namespace;
        }

        // 处理继承
        $extend = $input->getOption('extend');

        if (empty($extend)) {
            $extend = "\\think\\Controller";
        } else {
            if (!preg_match("/\\\/", $extend)) {
                if (!empty($module)) {
                    $extend = "\\" . APP_NAMESPACE . "\\" . $module . "\\" . 'controller' . "\\" . $extend;
                }
            }
        }


        $result = $this->build($namespace, $extend);
        $output->writeln("output:" . $result);
    }

    private function build($namespace, $extend)
    {
        $tpl = file_get_contents(THINK_PATH . 'tpl' . DS . 'make_controller.tpl');

        // comminute namespace
        $allNamespace = self::formatNameSpace($namespace);
        $namespace = implode('\\', $allNamespace[0]);
        $className = ucwords($allNamespace[1]);

        // 处理内容
        $content = str_replace("{%extend%}", $extend,
            str_replace("{%className%}", $className,
                str_replace("{%namespace%}", $namespace, $tpl)
            )
        );

        // 处理文件夹
        $path = '';
        foreach ($allNamespace[0] as $key => $value) {
            if ($key >= 1) {
                self::buildDir($path . $value);
                $path .= $value . "\\";
            }
        }

        // 处理文件
        $file = $path . $className . '.php';
        self::buildFile($file, $content);

        return APP_PATH . $file;
    }
}
