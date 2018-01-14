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

class AppAwareCommand extends Command implements AppAwareInterface
{
    /**
     * @var App
     */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function setApp(App $application)
    {
        $this->app = $application;
    }

    /**
     * {@inheritdoc}
     */
    public function getApp()
    {
        return $this->app;
    }
}