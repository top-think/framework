<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Slince <taosikai@yeah.net>
// +---------------------------------------

namespace think\console;

use think\App;

interface AppAwareInterface
{
    /**
     * 设置 application.
     *
     * @param App $application
     */
    public function setApp(App $application);

    /**
     * 获取 application.
     *
     * @return App
     */
    public function getApp();
}