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

use think\Container;
use think\route\Dispatch;

class Callback extends Dispatch
{
    public function run()
    {
        // 执行回调方法
        $vars = array_merge($this->app['request']->param(), $this->param);

        return Container::getInstance()->invoke($this->dispatch, $vars);
    }

}
