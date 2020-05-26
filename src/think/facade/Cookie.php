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
 * @see \think\Cookie
 * @package think\facade
 * @mixin \think\Cookie
 * @method mixed get(mixed $name = '', string $default = null) 获取cookie
 * @method bool has(string $name): bool 是否存在Cookie参数
 * @method void set(string $name, string $value, mixed $option = null): void Cookie 设置
 * @method void forever(string $name, string $value = '', mixed $option = null): void 永久保存Cookie数据
 * @method void delete(string $name): void Cookie删除
 * @method array getCookie(): array 获取cookie保存数据
 * @method void save(): void 保存Cookie
 */
class Cookie extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'cookie';
    }
}
