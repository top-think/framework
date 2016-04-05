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

namespace think\console\command\cache;

use think\console\command\Command;
use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;

class Clear extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cache:clear')
             ->setDescription('Clear Application Runtime');
    }

    protected function execute(Input $input, Output $output)
    {
        if (APP_DEBUG) {
            $output->writeln("Open debug, do not need clear.");
            return;
        }
        $templateDir = RUNTIME_PATH . '\temp';
        if (!file_exists($templateDir)) {
            $output->writeln("Runtime does not exist.");
            return;
        }
        $this->rmdirs($templateDir);
        $output->writeln("Clear done!");
    }

    protected function rmdirs($dir)
    {
         $dir_arr = scandir($dir);
         foreach($dir_arr as $key=>$val){
             if($val == '.' || $val == '..'){}
             else {
                 if(is_dir($dir.'/'.$val))
                 {
                     if(@rmdir($dir.'/'.$val) == 'true'){}
                    else
                     rmdirs($dir.'/'.$val);
                 }
                 else
                 unlink($dir.'/'.$val);
             }
         }
     }
}
