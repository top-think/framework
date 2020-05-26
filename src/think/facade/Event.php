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
 * @see \think\Event
 * @package think\facade
 * @mixin \think\Event
 * @method \think\Event listenEvents(array $events) 批量注册事件监听
 * @method \think\Event listen(string $event, mixed $listener, bool $first = false) 注册事件监听
 * @method bool hasListener(string $event): bool 是否存在事件监听
 * @method void remove(string $event): void 移除事件监听
 * @method \think\Event bind(array $events) 指定事件别名标识 便于调用
 * @method \think\Event subscribe(mixed $subscriber) 注册事件订阅者
 * @method \think\Event observe(string|object $observer, null|string $prefix = '') 自动注册事件观察者
 * @method mixed trigger(string|object $event, mixed $params = null, bool $once = false) 触发事件
 * @method mixed until($event, $params = null) 触发事件(只获取一个有效返回值)
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
