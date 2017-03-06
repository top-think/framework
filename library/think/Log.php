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

namespace think;

use think\exception\ClassNotFoundException;

class Log
{
    const LOG    = 'log';
    const ERROR  = 'error';
    const INFO   = 'info';
    const SQL    = 'sql';
    const NOTICE = 'notice';
    const ALERT  = 'alert';
    const DEBUG  = 'debug';

    // 日志信息
    protected $log = [];
    // 配置参数
    protected $config = [];
    // 日志类型
    protected $type = ['log', 'error', 'info', 'sql', 'notice', 'alert', 'debug'];
    // 日志写入驱动
    protected $driver;

    // 当前日志授权key
    protected $key;

    /**
     * 日志初始化
     * @param array $config
     */
    public function init($config = [])
    {
        $type         = isset($config['type']) ? $config['type'] : 'File';
        $class        = false !== strpos($type, '\\') ? $type : '\\think\\log\\driver\\' . ucwords($type);
        $this->config = $config;
        unset($config['type']);
        if (class_exists($class)) {
            $this->driver = new $class($config);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
        // 记录初始化信息
        Facade::make('app')->isDebug() && $this->record('[ LOG ] INIT ' . $type, 'info');
        return $this;
    }

    /**
     * 获取日志信息
     * @param string $type 信息类型
     * @return array
     */
    public function getLog($type = '')
    {
        return $type ? $this->log[$type] : $this->log;
    }

    /**
     * 记录调试信息
     * @param mixed  $msg  调试信息
     * @param string $type 信息类型
     * @return void
     */
    public function record($msg, $type = 'log')
    {
        $this->log[$type][] = $msg;
        if (PHP_SAPI == 'cli' && count($this->log[$type]) > 100) {
            // 命令行下面日志写入改进
            $this->save();
        }
        return $this;
    }

    /**
     * 清空日志信息
     * @return void
     */
    public function clear()
    {
        $this->log = [];
        return $this;
    }

    /**
     * 当前日志记录的授权key
     * @param string  $key  授权key
     * @return void
     */
    public function key($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * 检查日志写入权限
     * @param array  $config  当前日志配置参数
     * @return bool
     */
    public function check($config)
    {
        if ($this->key && !empty($config['allow_key']) && !in_array($this->key, $config['allow_key'])) {
            return false;
        }
        return true;
    }

    /**
     * 保存调试信息
     * @return bool
     */
    public function save()
    {
        if (!empty($this->log)) {
            if (is_null($this->driver)) {
                $this->init(Facade::make('app')->config('log'));
            }

            if (!$this->check($this->config)) {
                // 检测日志写入权限
                return false;
            }

            if (empty($this->config['level'])) {
                // 获取全部日志
                $log = $this->log;
                if (!Facade::make('app')->isDebug() && isset($log['debug'])) {
                    unset($log['debug']);
                }
            } else {
                // 记录允许级别
                $log = [];
                foreach ($this->config['level'] as $level) {
                    if (isset($this->log[$level])) {
                        $log[$level] = $this->log[$level];
                    }
                }
            }

            $result = $this->driver->save($log);
            if ($result) {
                $this->log = [];
            }

            return $result;
        }
        return true;
    }

    /**
     * 实时写入日志信息 并支持行为
     * @param mixed  $msg  调试信息
     * @param string $type 信息类型
     * @param bool   $force 是否强制写入
     * @return bool
     */
    public function write($msg, $type = 'log', $force = false)
    {
        // 封装日志信息
        if (true === $force || empty($this->config['level'])) {
            $log[$type][] = $msg;
        } elseif (in_array($type, $this->config['level'])) {
            $log[$type][] = $msg;
        } else {
            return false;
        }

        // 监听log_write
        Facade::make('hook')->listen('log_write', $log);
        if (is_null($this->driver)) {
            $this->init(Facade::make('app')->config('log'));
        }
        // 写入日志
        return $this->driver->save($log, false);
    }

    /**
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (in_array($method, $this->type)) {
            array_push($args, $method);
            return call_user_func_array([$this, 'record'], $args);
        }
    }

}
