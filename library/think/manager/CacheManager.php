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

namespace think\manager;

use think\App;
use think\cache\Driver;
use think\Config;
use think\Log;

class CacheManager
{
    protected $instance = [];

    /**
     * 操作句柄
     * @var object
     * @access protected
     */
    protected $handler;

    /**
     * 连接缓存
     * @access public
     * @param array         $options  配置数组
     * @param bool|string   $name 缓存连接标识 true 强制重新连接
     * @return Driver
     */
    public function connect(array $options = [], $name = false)
    {
        $type = !empty($options['type']) ? $options['type'] : 'File';
        if (false === $name) {
            $name = md5(serialize($options));
        }

        if (true === $name || !isset($this->instance[$name])) {
            $class = false !== strpos($type, '\\') ? $type : '\\think\\cache\\driver\\' . ucwords($type);

            // 记录初始化信息
            App::isDebug() && Log::record('[ CACHE ] INIT ' . $type, 'info');
            if (true === $name) {
                return new $class($options);
            } else {
                $this->instance[$name] = new $class($options);
            }
        }
        $this->handler = $this->instance[$name];
        return $this->handler;
    }

    /**
     * 自动初始化缓存
     * @access public
     * @param array         $options  配置数组
     * @return void
     */
    public function init(array $options = [])
    {
        if (is_null($this->handler)) {
            // 自动初始化缓存
            if (!empty($options)) {
                $this->connect($options);
            } elseif ('complex' == Config::get('cache.type')) {
                $this->connect(Config::get('cache.default'));
            } else {
                $this->connect(Config::get('cache'));
            }
        }
        return $this->handler;
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     * @access public
     * @param string $name 缓存标识
     * @return Driver
     */
    public function store($name = '')
    {
        if ('' !== $name && 'complex' == Config::get('cache.type')) {
            $this->connect(Config::get('cache.' . $name), strtolower($name));
        }
        return $this->handler;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->init(), $method], $args);
    }

}
