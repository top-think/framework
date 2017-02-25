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

namespace think;

class Facade
{
    //  对象实例
    protected static $instance = [];

    /**
     * 创建对象实例
     * @static
     * @access protected
     * @param mixed         $config 连接配置
     * @return object
     */
    protected static function createFacade()
    {
        $name = static::class;

        if (!isset(self::$instance[$name])) {
            $class = static::getFacadeClass() ?: '\\think\\manager\\' . basename(str_replace('\\', '/', $name) . 'Manager');

            self::$instance[$name] = new $class();
        }
        return self::$instance[$name];
    }

    protected static function getFacadeClass()
    {
    }

    /**
     * 初始化
     * @access public
     * @return object
     */
    public static function instance()
    {
        return self::createFacade();
    }

    // 调用驱动类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::createFacade(), $method], $params);
    }
}
