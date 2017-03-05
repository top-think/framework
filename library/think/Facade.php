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

    protected static $bind = [];

    public static function bind($name, $class = null)
    {
        if (is_array($name)) {
            self::$bind = array_merge(self::$bind, $name);
        } else {
            self::$bind[$name] = $class;
        }
    }

    /**
     * 创建对象实例
     * @static
     * @access protected
     * @return object
     */
    protected static function createFacade($name = '', $args = [])
    {
        $name        = $name ?: static::class;
        $facadeClass = static::getFacadeClass();
        if ($facadeClass) {
            $class = $facadeClass;
        } elseif (isset(self::$bind[$name])) {
            $class = self::$bind[$name];
        } else {
            $class = $name;
        }
        return Container::getInstance()->make($class, $args);
    }

    protected static function getFacadeClass()
    {}

    /**
     * 带参数实例化当前Facade类
     * @access public
     * @return object
     */
    public static function instance(...$args)
    {
        return self::createFacade('', $args);
    }

    /**
     * 指定某个Facade类进行实例化
     * @access public
     * @param string    $class 类名
     * @return object
     */
    public static function make($class)
    {
        return self::createFacade($class);
    }

    // 调用类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::createFacade(), $method], $params);
    }
}
