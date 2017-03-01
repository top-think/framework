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
        $name = $name ?: static::class;

        if (!isset(self::$instance[$name])) {
            $class = static::getFacadeClass() ?: self::$bind[$name];

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
        $reflect     = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();
        if ($constructor) {
            $args = self::bindParams($constructor, $vars);
        } else {
            $args = [];
        }
        return $reflect->newInstanceArgs($args);
    }

    /**
     * 绑定参数
     * @access public
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array                                 $vars    变量
     * @return array
     */
    private static function bindParams($reflect, $vars = [])
    {
        $args = [];
        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type = key($vars) === 0 ? 1 : 0;
        if ($reflect->getNumberOfParameters() > 0) {
            $params = $reflect->getParameters();
            foreach ($params as $param) {
                $args[] = self::getParamValue($param, $type);
            }
        }
        return $args;
    }

    private static function getParamValue($param, $type)
    {
        $name  = $param->getName();
        $class = $param->getClass();
        if ($class) {
            $className = $class->getName();
            if (isset(self::$instance[$className])) {
                $result = self::$instance[$className];
            } else {
                $name = array_search($className, self::$bind);
                if (isset(self::$instance[$name])) {
                    $result = self::$instance[$name];
                } else {
                    $result = method_exists($className, 'instance') ? $className::instance() : new $className;
                }
            }
        } elseif (1 == $type && !empty($vars)) {
            $result = array_shift($vars);
        } elseif (0 == $type && isset($vars[$name])) {
            $result = $vars[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $result = $param->getDefaultValue();
        } else {
            throw new \InvalidArgumentException('method param miss:' . $name);
        }
        return $result;
    }
    protected static function getFacadeClass()
    {
    }

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
        if (false === strpos($class, '\\')) {
            $class = '\\think\\facade\\' . $class;
        }
        return self::createFacade(ltrim($class, '\\'));
    }

    // 调用类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::createFacade(), $method], $params);
    }
}
