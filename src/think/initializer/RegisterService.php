<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\initializer;


use think\App;

class RegisterService
{
    public function init(App $app)
    {
        if (is_file($app->getRuntimePath() . 'services.php')) {
            $services = include $app->getRuntimePath() . 'services.php';

            foreach ($services as $service) {
                $app->register($service);
            }
        }
    }
}