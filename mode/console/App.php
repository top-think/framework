<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think;


use think\Console;

class App
{
    /**
     * 执行应用程序
     * @access public
     * @return void
     */
    public static function run()
    {
        // 实例化console
        $console = new Console('Think Console', '0.1');
        // 读取指令集
        if (is_file(APP_PATH . 'command' . EXT)) {
            $commands = include APP_PATH . 'command' . EXT;
            if (is_array($commands)) {
                foreach ($commands as $command) {
                    if (class_exists($command) && is_subclass_of($command, "\\think\\console\\command\\Command")) {
                        // 注册指令
                        $console->add(new $command());
                    }
                }
            }
        }
        // 运行
        $console->run();
    }
}