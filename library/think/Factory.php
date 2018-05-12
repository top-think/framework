<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\ClassNotFoundException;

trait Factory
{
    /**
     * 创建工厂对象实例
     * @access protected
     * @param  string $name         工厂类名
     * @param  mixed  $option       实例化参数
     * @param  string $namespace    默认命名空间
     * @return mixed
     */
    protected static function instanceFactory($name, $option = null, $namespace = '')
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, is_null($option) ? [] : [$option]);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }
}
