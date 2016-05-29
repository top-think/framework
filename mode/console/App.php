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
        self::init();

        // 实例化console
        $console = new Console('Think Console', '0.1');
        // 读取指令集
        if (is_file(CONF_PATH . 'command' . EXT)) {
            $commands = include CONF_PATH . 'command' . EXT;
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

    private static function init()
    {
        // 加载初始化文件
        if (is_file(APP_PATH . 'init' . EXT)) {
            include APP_PATH . 'init' . EXT;

            // 加载模块配置
            $config = Config::get();
        } else {
            // 加载模块配置
            $config = Config::load(CONF_PATH . 'config' . CONF_EXT);

            // 加载应用状态配置
            if ($config['app_status']) {
                $config = Config::load(CONF_PATH . $config['app_status'] . CONF_EXT);
            }

            // 读取扩展配置文件
            if ($config['extra_config_list']) {
                foreach ($config['extra_config_list'] as $name => $file) {
                    $filename = CONF_PATH . $file . CONF_EXT;
                    Config::load($filename, is_string($name) ? $name : pathinfo($filename, PATHINFO_FILENAME));
                }
            }

            // 加载别名文件
            if (is_file(CONF_PATH . 'alias' . EXT)) {
                Loader::addMap(include CONF_PATH . 'alias' . EXT);
            }

            // 加载行为扩展文件
            if (is_file(CONF_PATH . 'tags' . EXT)) {
                Hook::import(include CONF_PATH . 'tags' . EXT);
            }

            // 加载公共文件
            if (is_file(APP_PATH . 'common' . EXT)) {
                include APP_PATH . 'common' . EXT;
            }
        }

        // 注册根命名空间
        if (!empty($config['root_namespace'])) {
            Loader::addNamespace($config['root_namespace']);
        }

        // 加载额外文件
        if (!empty($config['extra_file_list'])) {
            foreach ($config['extra_file_list'] as $file) {
                $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                if (is_file($file)) {
                    include_once $file;
                }
            }
        }

        // 设置系统时区
        date_default_timezone_set($config['default_timezone']);

        // 监听app_init
        Hook::listen('app_init');
    }
}
