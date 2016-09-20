<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\App;
use think\exception\ClassNotFoundException;

class Session
{
    protected static $prefix = '';
    protected static $init   = null;

    /**
     * 设置或者获取session作用域（前缀）
     * @param string $prefix
     * @return string|void
     */
    public static function prefix($prefix = '')
    {
        if (empty($prefix) && null !== $prefix) {
            return self::$prefix;
        } else {
            self::$prefix = $prefix;
        }
    }

    /**
     * session初始化
     * @param array $config
     * @return void
     * @throws \think\Exception
     */
    public static function init(array $config = [])
    {
        if (empty($config)) {
            $config = Config::get('session');
        }
        // 记录初始化信息
        App::$debug && Log::record('[ SESSION ] INIT ' . var_export($config, true), 'info');
        $isDoStart = false;
        if (isset($config['use_trans_sid'])) {
            ini_set('session.use_trans_sid', $config['use_trans_sid'] ? 1 : 0);
        }

        // 启动session
        if (!empty($config['auto_start']) && PHP_SESSION_ACTIVE != session_status()) {
            ini_set('session.auto_start', 0);
            $isDoStart = true;
        }

        if (isset($config['prefix'])) {
            self::$prefix = $config['prefix'];
        }
        if (isset($config['var_session_id']) && isset($_REQUEST[$config['var_session_id']])) {
            session_id($_REQUEST[$config['var_session_id']]);
        } elseif (isset($config['id']) && !empty($config['id'])) {
            session_id($config['id']);
        }
        if (isset($config['name'])) {
            session_name($config['name']);
        }
        if (isset($config['path'])) {
            session_save_path($config['path']);
        }
        if (isset($config['domain'])) {
            ini_set('session.cookie_domain', $config['domain']);
        }
        if (isset($config['expire'])) {
            ini_set('session.gc_maxlifetime', $config['expire']);
            ini_set('session.cookie_lifetime', $config['expire']);
        }

        if (isset($config['use_cookies'])) {
            ini_set('session.use_cookies', $config['use_cookies'] ? 1 : 0);
        }
        if (isset($config['cache_limiter'])) {
            session_cache_limiter($config['cache_limiter']);
        }
        if (isset($config['cache_expire'])) {
            session_cache_expire($config['cache_expire']);
        }
        if (!empty($config['type'])) {
            // 读取session驱动
            $class = false !== strpos($config['type'], '\\') ? $config['type'] : '\\think\\session\\driver\\' . ucwords($config['type']);

            // 检查驱动类
            if (!class_exists($class) || !session_set_save_handler(new $class($config))) {
                throw new ClassNotFoundException('error session handler:' . $class, $class);
            }
        }
        if ($isDoStart) {
            session_start();
            self::$init = true;
        } else {
            self::$init = false;
        }
    }

    /**
     * session自动启动或者初始化
     * @return void
     */
    public static function boot()
    {
        if (is_null(self::$init)) {
            self::init();
        } elseif (false === self::$init) {
            session_start();
            self::$init = true;
        }
    }

    /**
     * session设置
     * @param string        $name session名称
     * @param mixed         $value session值
     * @param string|null   $prefix 作用域（前缀）
     * @return void
     */
    public static function set($name, $value = '', $prefix = null)
    {
        empty(self::$init) && self::boot();
        $prefix = !is_null($prefix) ? $prefix : self::$prefix;

        $keys = explode('.', $name);
        if ($prefix) {
            array_unshift($keys, $prefix);
        }

        self::_set($_SESSION, $keys, $value);
    }

    /**
     * session数组设置
     * @param array &$array $_SESSION
     * @param array $keys   session名称
     * @param mixed $value  session值
     */
    private static function _set(&$array, $keys, $value)
    {
        foreach ($keys as $key) {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array = $value;
    }

    /**
     * session获取
     * @param string        $name session名称
     * @param string|null   $prefix 作用域（前缀）
     * @return mixed
     */
    public static function get($name = '', $prefix = null)
    {
        empty(self::$init) && self::boot();
        $prefix = !is_null($prefix) ? $prefix : self::$prefix;

        $keys = explode('.', $name);
        if ($prefix) {
            array_unshift($keys, $prefix);
        }

        return self::_get($_SESSION, $keys);
    }

    /**
     * 获取session数组
     * @param  array $array session数组
     * @param  array $keys  session名称
     * @return mixed
     */
    private static function _get($array, $keys)
    {
        foreach (array_filter($keys) as $key) {
            if (!isset($array[$key])) {
                return null;
            }
            $array = $array[$key];
        }

        return $array;
    }

    /**
     * 删除session数据
     * @param string        $name session名称
     * @param string|null   $prefix 作用域（前缀）
     * @return void
     */
    public static function delete($name, $prefix = null)
    {
        empty(self::$init) && self::boot();
        $prefix = !is_null($prefix) ? $prefix : self::$prefix;

        $keys = explode('.', $name);
        if ($prefix) {
            array_unshift($keys, $prefix);
        }

        self::_delete($_SESSION, $keys);
    }

    /**
     * 删除session数组元素
     * @param  array &$array session数组
     * @param  array $keys   session名称
     * @return void
     */
    private static function _delete(&$array, $keys)
    {
        $target = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return;
            }
            $array = &$array[$key];
        }

        unset($array[$target]);
    }

    /**
     * 清空session数据
     * @param string|null   $prefix 作用域（前缀）
     * @return void
     */
    public static function clear($prefix = null)
    {
        empty(self::$init) && self::boot();
        $prefix = !is_null($prefix) ? $prefix : self::$prefix;
        if ($prefix) {
            unset($_SESSION[$prefix]);
        } else {
            $_SESSION = [];
        }
    }

    /**
     * 判断session数据
     * @param string        $name session名称
     * @param string|null   $prefix
     * @return bool
     */
    public static function has($name, $prefix = null)
    {
        empty(self::$init) && self::boot();
        $prefix = !is_null($prefix) ? $prefix : self::$prefix;

        $keys = strpos($name, '.') ? explode('.', $name) : [$name];
        if ($prefix) {
            array_unshift($keys, $prefix);
        }

        return self::_has($_SESSION, $keys);
    }

    /**
     * 判断session数组元素是否存在
     * @param  array  $array session数组
     * @param  array  $keys  session名称
     * @return boolean
     */
    private static function _has($array, $keys)
    {
        $target = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return false;
            }
            $array = &$array[$key];
        }

        return isset($array[$target]);
    }

    /**
     * 启动session
     * @return void
     */
    public static function start()
    {
        session_start();
        self::$init = true;
    }

    /**
     * 销毁session
     * @return void
     */
    public static function destroy()
    {
        if (!empty($_SESSION)) {
            $_SESSION = [];
        }
        session_unset();
        session_destroy();
        self::$init = null;
    }

    /**
     * 重新生成session_id
     * @param bool $delete 是否删除关联会话文件
     * @return void
     */
    private static function regenerate($delete = false)
    {
        session_regenerate_id($delete);
    }

    /**
     * 暂停session
     * @return void
     */
    public static function pause()
    {
        // 暂停session
        session_write_close();
        self::$init = false;
    }
}
