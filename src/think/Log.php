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

namespace think;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 日志管理类
 */
class Log implements LoggerInterface
{
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    const SQL       = 'sql';

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 日志信息
     * @var array
     */
    protected $log = [];

    /**
     * 日志通道
     * @var string
     */
    protected $channel;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 日志写入驱动
     * @var array
     */
    protected $driver = [];

    /**
     * 日志授权key
     * @var string
     */
    protected $key;

    /**
     * 是否允许日志写入
     * @var bool
     */
    protected $allowWrite = true;

    /**
     * 构造方法
     * @access public
     */
    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->config = $app->config->get('log');

        $this->channel();
    }

    /**
     * 切换日志通道
     * @access public
     * @param  string $name 日志通道名
     * @return $this
     */
    public function channel(string $name = '')
    {
        if ('' == $name) {
            $name = $this->config['default'] ?? 'think';
        }

        if (!isset($this->config['channels'][$name])) {
            throw new InvalidArgumentException('Undefined log config:' . $name);
        }

        $this->channel = $name;
        return $this;
    }

    /**
     * 实例化日志写入驱动
     * @access public
     * @param  string $name 日志通道名
     * @return object
     */
    protected function driver(string $name = '')
    {
        $name = $name ?: $this->channel;

        if (!isset($this->driver[$name])) {
            $config = $this->config['channels'][$name];
            $type   = !empty($config['type']) ? $config['type'] : 'File';

            $this->driver[$name] = App::factory($type, '\\think\\log\\driver\\', $config);
        }

        return $this->driver[$name];
    }

    /**
     * 获取日志信息
     * @access public
     * @param  string $channel 日志通道
     * @return array
     */
    public function getLog(string $channel = ''): array
    {
        $channel = $channel ?: $this->channel;
        return $this->log[$channel] ?? [];
    }

    /**
     * 记录日志信息
     * @access public
     * @param  mixed  $msg       日志信息
     * @param  string $type      日志级别
     * @param  array  $context   替换内容
     * @return $this
     */
    public function record($msg, string $type = 'info', array $context = [])
    {
        if (!$this->allowWrite) {
            return;
        }

        if (is_string($msg) && !empty($context)) {
            $replace = [];
            foreach ($context as $key => $val) {
                $replace['{' . $key . '}'] = $val;
            }

            $msg = strtr($msg, $replace);
        }

        if ($this->app->runningInConsole()) {
            if (empty($this->config['level']) || in_array($type, $this->config['level'])) {
                // 命令行日志实时写入
                $this->write($msg, $type, true);
            }
        } elseif (isset($this->config['type_channel'][$type])) {
            $channels = (array) $this->config['type_channel'][$type];
            foreach ($channels as $channel) {
                $this->log[$channel][$type][] = $msg;
            }
        } else {
            $this->log[$this->channel][$type][] = $msg;
        }

        return $this;
    }

    /**
     * 记录批量日志信息
     * @access public
     * @param  array  $msg  日志信息
     * @param  string $type 日志级别
     * @return $this
     */
    public function append(array $log, string $type = 'info')
    {
        if (!$this->allowWrite || empty($log)) {
            return $this;
        }

        if (isset($this->log[$this->channel][$type])) {
            $this->log[$this->channel][$type] += $log;
        } else {
            $this->log[$this->channel][$type] = $log;
        }

        return $this;
    }

    /**
     * 清空日志信息
     * @access public
     * @param  string  $channel 日志通道名
     * @return $this
     */
    public function clear(string $channel = '')
    {
        if ($channel) {
            $this->log[$channel] = [];
        } else {
            $this->log = [];
        }

        return $this;
    }

    /**
     * 当前日志记录的授权key
     * @access public
     * @param  string  $key  授权key
     * @return $this
     */
    public function key(string $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * 检查日志写入权限
     * @access public
     * @param  array  $config  当前日志配置参数
     * @return bool
     */
    public function check(array $config): bool
    {
        if ($this->key && !empty($config['allow_key']) && !in_array($this->key, $config['allow_key'])) {
            return false;
        }

        return true;
    }

    /**
     * 关闭本次请求日志写入
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->allowWrite = false;
        $this->log        = [];

        return $this;
    }

    /**
     * 保存日志信息
     * @access public
     * @param  string $channel 日志通道名
     * @return bool
     */
    public function save(string $channel = ''): bool
    {
        if (empty($this->log) || !$this->allowWrite) {
            return true;
        }

        if (!$this->check($this->config)) {
            // 检测日志写入权限
            return false;
        }

        foreach ($this->log as $channel => $logs) {
            $log = [];

            foreach ($logs as $level => $info) {
                if (!$this->app->isDebug() && 'debug' == $level) {
                    continue;
                }

                if (empty($this->config['level']) || in_array($level, $this->config['level'])) {
                    $log[$level] = $info;
                }
            }

            $result = $this->driver($channel)->save($log);

            $this->log[$channel] = [];
        }

        return true;
    }

    /**
     * 实时写入日志信息 并支持行为
     * @access public
     * @param  mixed  $msg   调试信息
     * @param  string $type  日志级别
     * @param  bool   $force 是否强制写入
     * @return bool
     */
    public function write($msg, string $type = 'info', bool $force = false): bool
    {
        // 封装日志信息
        if (empty($this->config['level'])) {
            $force = true;
        }

        $log = [];

        if (true === $force || in_array($type, $this->config['level'])) {
            $log[$type][] = $msg;
        } else {
            return false;
        }

        // 监听LogWrite
        $this->app->event->trigger('LogWrite', $log);

        // 写入日志
        return $this->driver()->save($log, false);
    }

    /**
     * 记录日志信息
     * @access public
     * @param  string $level     日志级别
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $this->record($message, $level, $context);
    }

    /**
     * 记录emergency信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录警报信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录紧急情况
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录错误信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录warning信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录notice信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录一般信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录sql信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function sql($message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }
}
