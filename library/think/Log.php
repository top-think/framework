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

class Log
{
    const LOG    = 'log';
    const ERROR  = 'error';
    const INFO   = 'info';
    const SQL    = 'sql';
    const NOTICE = 'notice';
    const ALERT  = 'alert';

    // 日志信息
    protected static $log = [];
    // 配置参数
    protected static $config = [];
    // 日志类型
    protected static $type = ['log', 'error', 'info', 'sql', 'notice', 'alert'];
    // 日志写入驱动
    protected static $driver;
    // 通知发送驱动
    protected static $alarm;
    // 当前日志授权key
    protected static $key;

    /**
     * 日志初始化
     * @return void
     */
    public static function init($config = [])
    {
        $type         = isset($config['type']) ? $config['type'] : 'File';
        $class        = (!empty($config['namespace']) ? $config['namespace'] : '\\think\\log\\driver\\') . ucwords($type);
        self::$config = $config;
        unset($config['type']);
        self::$driver = new $class($config);
        // 记录初始化信息
        APP_DEBUG && Log::record('[ LOG ] INIT ' . $type . ': ' . var_export($config, true), 'info');
    }

    /**
     * 通知初始化
     * @return void
     */
    public static function alarm($config = [])
    {
        $type  = isset($config['type']) ? $config['type'] : 'Email';
        $class = (!empty($config['namespace']) ? $config['namespace'] : '\\think\\log\\alarm\\') . ucwords($type);
        unset($config['type']);
        self::$alarm = new $class($config['alarm']);
        // 记录初始化信息
        APP_DEBUG && Log::record('[ CACHE ] ALARM ' . $type . ': ' . var_export($config, true), 'info');
    }

    /**
     * 获取日志信息
     * @param string $type 信息类型
     * @return array
     */
    public static function getLog($type = '')
    {
        return $type ? self::$log[$type] : self::$log;
    }

    /**
     * 记录调试信息
     * @param mixed  $msg  调试信息
     * @param string $type 信息类型
     * @return void
     */
    public static function record($msg, $type = 'log')
    {
        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }
        self::$log[$type][] = $msg;
    }

    /**
     * 清空日志信息
     * @return void
     */
    public static function clear()
    {
        self::$log = [];
    }

    /**
     * 当前日志记录的授权key
     * @param string  $key  授权key
     * @return void
     */
    public static function key($key)
    {
        self::$key = $key;
    }

    /**
     * 检查日志写入权限
     * @param array  $config  当前日志配置参数
     * @return bool
     */
    public static function check($config)
    {

        if (self::$key && !empty($config['allow_key']) && !in_array(self::$key, $config['allow_key'])) {
            return false;
        }
        return true;
    }

    /**
     * 保存调试信息
     * @return bool
     */
    public static function save()
    {
        if (!empty(self::$log)) {
            if (is_null(self::$driver)) {
                self::init(Config::get('log'));
            }

            if (!self::check(self::$config)) {
                // 检测日志写入权限
                return false;
            }
            $result = self::$driver->save(self::$log);

            if ($result) {
                self::$log = [];
            }

            return $result;
        }
        return true;
    }

    /**
     * 实时写入日志信息 并支持行为
     * @param mixed  $msg  调试信息
     * @param string $type 信息类型
     * @return bool
     */
    public static function write($msg, $type = 'log')
    {
        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }
        // 封装日志信息
        $log[$type][] = $msg;

        // 监听log_write
        Hook::listen('log_write', $log);
        if (is_null(self::$driver)) {
            self::init(Config::get('log'));
        }
        // 写入日志
        return self::$driver->save($log);
    }

    /**
     * 发送预警通知
     * @param mixed $msg 调试信息
     * @return void
     */
    public static function send($msg)
    {
        self::$alarm && self::$alarm->send($msg);
    }

    /**
     * 静态调用
     * @return void
     */
    public static function __callStatic($method, $args)
    {
        if (in_array($method, self::$type)) {
            array_push($args, $method);
            return call_user_func_array('\\think\\Log::record', $args);
        }
    }

}
