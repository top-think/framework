<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

// ThinkPHP 实际引导文件
// 加载基础文件
require __DIR__ . '/base.php';
require CORE_PATH . 'Loader.php';

// 加载环境变量配置文件
if (is_file(ROOT_PATH . 'env' . EXT)) {
    $env = include ROOT_PATH . 'env' . EXT;
    foreach ($env as $key => $val) {
        $name = ENV_PREFIX . $key;
        if (is_bool($val)) {
            $val = $val ? 1 : 0;
        }
        putenv("$name=$val");
    }
}

// 注册命名空间定义
Loader::addNamespace([
    'think'         => LIB_PATH . 'think' . DS,
    'behavior'      => LIB_PATH . 'behavior' . DS,
    'traits'        => LIB_PATH . 'traits' . DS,
]);

// 注册自动加载
Loader::register();

// 加载别名定义
Loader::addMap(include THINK_PATH . 'alias' . EXT);

// 注册错误和异常处理机制
Error::register();

// 加载模式配置文件
Config::set(include THINK_PATH . 'convention' . EXT);

// 是否自动运行
if (APP_AUTO_RUN) {
    App::run()->send();
}
