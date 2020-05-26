<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\facade;

use think\Facade;

/**
 * @see \think\Env
 * @package think\facade
 * @mixin \think\Env
 * @method void load(string $file): void 读取环境变量定义文件
 * @method mixed get(string $name = null, mixed $default = null) 获取环境变量值
 * @method void set(string|array $env, mixed $value = null): void 设置环境变量值
 * @method bool has(string $name): bool 检测是否存在环境变量
 * @method void __set(string $name, mixed $value): void 设置环境变量
 * @method mixed __get(string $name) 获取环境变量
 * @method bool __isset(string $name): bool 检测是否存在环境变量
 * @method void offsetSet($name, $value): void
 * @method bool offsetExists($name): bool
 * @method mixed offsetUnset($name)
 * @method mixed offsetGet($name)
 */
class Env extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'env';
    }
}
