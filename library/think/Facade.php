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
     * @return object
     */
    protected static function createFacade($args = [])
    {
        $name = static::class;

        if (!isset(self::$instance[$name])) {
            $class = static::getFacadeClass() ?: '\\think\\manager\\' . basename(str_replace('\\', '/', $name) . 'Manager');

            self::$instance[$name] = self::invokeClass($class, $args);
        }
        return self::$instance[$name];
    }

    /**
     * 调用反射执行类的实例化
     * @access public
     * @param string    $class 类名
     * @param array     $vars  变量
     * @return mixed
     */
    protected static function invokeClass($class, $vars = [])
    {
        $reflect = new \ReflectionClass($class);
        return $reflect->newInstanceArgs($reflect->getConstructor() ? $vars : []);
    }

    protected static function getFacadeClass()
    {
    }

    /**
     * 初始化
     * @access public
     * @return object
     */
    public static function instance(...$args)
    {
        return self::createFacade($args);
    }

    // 调用类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::createFacade(), $method], $params);
    }
}
