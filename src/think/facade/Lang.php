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

namespace think\facade;

use think\Facade;

/**
 * @see \think\Lang
 * @mixin \think\Lang
 * @method void setLangSet($range = '') static 设定当前的语言
 * @method string getLangSet() static 获取当前的语言
 * @method array load(mixed $file, string $range = '') static 加载语言定义
 * @method bool has(string $name, string $range = '') static 获取语言定义
 * @method mixed get(string $name = null, array $vars = [], string $range = '') static 获取语言定义
 * @method void detect() static 自动侦测设置获取语言选择
 * @method void saveToCookie(string $lang = null) static 设置当前语言到Cookie
 */
class Lang extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'lang';
    }
}
