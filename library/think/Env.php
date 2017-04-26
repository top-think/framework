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

class Env
{
    /**
     * 获取环境变量值
     * @param string    $name 环境变量名（支持二级 .号分割）
     * @param string    $default  默认值
     * @return mixed
     */
    public static function get($name, $default = null)
    {
        $result = getenv('PHP_' . strtoupper(str_replace('.', '_', $name)));

        if (false !== $result) {
            return $result;
        } else {
            return $default;
        }
    }

    /**
     * 设置环境变量值
     * @param string|array  $env   环境变量
     * @param string        $value  值
     * @return void
     */
    public static function set($env, $value = null)
    {
        if (!is_array($env)) {
            $env = [$name => $value];
        }

        foreach ($env as $key => $val) {
            $name = 'PHP_' . strtoupper($key);
            putenv("$name=$val");
        }
    }
}
