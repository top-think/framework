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
 * @see \think\Event
 * @mixin \think\Event
 * @method \think\Event bind(mixed $name, mixed $event = null) static 指定事件别名
 * @method \think\Event listen(string $event, mixed $listener) static 注册事件监听
 * @method \think\Event listenEvents(array $events) static 批量注册事件监听
 * @method \think\Event observe(mixed $observer) static 注册事件观察者
 * @method bool hasEvent(string $event) static 判断事件是否存在监听
 * @method void remove(string $event) static 移除事件监听
 * @method mixed trigger(string $event, mixed $params = null, bool $once = false) static 触发事件
 */
class Event extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'event';
    }
}
