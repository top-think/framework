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
use think\log\Channel;
use think\log\ChannelSet;

/**
 * @see \think\Log
 * @package think\facade
 * @mixin \think\Log
 * @method string|null getDefaultDriver() 默认驱动
 * @method mixed getConfig(null|string $name = null, mixed $default = null) 获取日志配置
 * @method array getChannelConfig(string $channel, null $name = null, null $default = null) 获取渠道配置
 * @method Channel|ChannelSet channel(string|array $name = null) driver() 的别名
 * @method mixed createDriver(string $name)
 * @method \think\Log clear(string|array $channel = '*') 清空日志信息
 * @method \think\Log close(string|array $channel = '*') 关闭本次请求日志写入
 * @method array getLog(string $channel = null) 获取日志信息
 * @method bool save() 保存日志信息
 * @method \think\Log record(mixed $msg, string $type = 'info', array $context = [], bool $lazy = true) 记录日志信息
 * @method \think\Log write(mixed $msg, string $type = 'info', array $context = []) 实时写入日志信息
 * @method Event listen($listener) 注册日志写入事件监听
 * @method void log(string $level, mixed $message, array $context = []) 记录日志信息
 * @method void emergency(mixed $message, array $context = []) 记录emergency信息
 * @method void alert(mixed $message, array $context = []) 记录警报信息
 * @method void critical(mixed $message, array $context = []) 记录紧急情况
 * @method void error(mixed $message, array $context = []) 记录错误信息
 * @method void warning(mixed $message, array $context = []) 记录warning信息
 * @method void notice(mixed $message, array $context = []) 记录notice信息
 * @method void info(mixed $message, array $context = []) 记录一般信息
 * @method void debug(mixed $message, array $context = []) 记录调试信息
 * @method void sql(mixed $message, array $context = []) 记录sql信息
 * @method mixed __call($method, $parameters)
 */
class Log extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'log';
    }
}
