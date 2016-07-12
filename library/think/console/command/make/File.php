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

use think\console\command\Make;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class File extends Make
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('make:file')
            ->setDescription('Create a new applcation class')
            ->addArgument('namespace', Argument::REQUIRED)
            ->addOption('layer', 'l', Option::VALUE_OPTIONAL, 'Layer Name', null)
            ->addOption('extend', 'e', Option::VALUE_OPTIONAL, 'Extend Base class', null);
    }

    protected function execute(Input $input, Output $output)
    {
        $namespace = $input->getArgument('namespace');
        $extend    = $input->getOption('extend');
        if (!$layer = $input->getOption('layer')) {
            // 自动识别layer
            $item  = explode('\\', $namespace);
            $layer = basename(dirname(implode(DS, $item)));
        }

        $result = $this->getResult($layer, $namespace, '', $extend);
        $output->writeln("output:" . $result);
    }

}
