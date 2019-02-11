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

namespace think\model\concern;

use think\App;
use think\Container;

/**
 * 模型事件处理
 */
trait ModelEvent
{
    /**
     * 模型回调
     * @var array
     */
    private static $event = [];

    /**
     * 模型事件观察
     * @var array
     */
    protected static $observe = ['after_read', 'before_write', 'after_write', 'before_insert', 'after_insert', 'before_update', 'after_update', 'before_delete', 'after_delete', 'before_restore', 'after_restore'];

    /**
     * 绑定模型事件观察者类
     * @var string
     */
    protected $observerClass;

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 清除回调方法
     * @access public
     * @return void
     */
    public static function flush(): void
    {
        self::$event[static::class] = [];
    }

    /**
     * 注册一个模型观察者
     *
     * @param  string  $class
     * @return void
     */
    protected static function observe(string $class): void
    {
        foreach (static::$observe as $event) {
            $call = 'on' . App::parseName($event, 1, false);

            if (method_exists($class, $call)) {
                $class = Container::getInstance()->invokeClass($class);

                self::$event[static::class][$event][] = [$class, $call];
            }
        }
    }

    /**
     * 当前操作的事件响应
     * @access protected
     * @param  bool $event  是否需要事件响应
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;
        return $this;
    }

    /**
     * 触发事件
     * @access protected
     * @param  string $event  事件名
     * @return bool
     */
    protected function trigger(string $event): bool
    {
        $class = static::class;

        if ($this->withEvent && isset(self::$event[$class][$event])) {
            foreach (self::$event[$class][$event] as $callback) {
                $result = Container::getInstance()->invoke($callback, [$this]);

                if (false === $result) {
                    return false;
                }
            }
        }

        return true;
    }
}
