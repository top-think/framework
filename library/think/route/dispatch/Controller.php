<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\route\dispatch;

use think\route\Dispatch;

class Controller extends Dispatch
{
    public function run()
    {
        // 执行控制器的操作方法
        $vars = array_merge($this->app['request']->param(), $this->param);

        return $this->app->action(
            $this->dispatch, $vars,
            $this->app->config('app.url_controller_layer'),
            $this->app->config('app.controller_suffix')
        );
    }

}
