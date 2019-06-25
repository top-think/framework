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
     * 是否允许日志写入
     * @var bool
     */
    protected $allowWrite = true;

    /**
     * 日志处理
     *
     * @var array
     */
    protected $processor = [
        '*' => [],
    ];

    /**
     * 构造方法
     * @access public
     */
    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->config = $app->config->get('log');

        if (isset($this->config['processor'])) {
            $this->processor($this->config['processor']);
        }

        $this->channel();
    }

    /**
     * 获取日志配置
     * @access public
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 注册一个日志回调处理
     *
     * @param  callable $callback 回调
     * @param  string   $channel  日志通道名
     * @return void
     */
    public function processor(callable $callback, string $channel = '*'): void
    {
        $this->processor[$channel][] = $callback;
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

        if (isset($this->config['channels'][$name]['processor'])) {
            $this->processor($this->config['channels'][$name]['processor'], $name);
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
     * @return bool
     */
    public function save(): bool
    {
        foreach ($this->log as $channel => $logs) {
            $result = $this->saveChannel($channel, $logs);

            if ($result) {
                $this->log[$channel] = [];
            }
        }

        return true;
    }

    /**
     * 保存某个通道的日志信息
     * @access protected
     * @param  string $channel 日志通道名
     * @param  array  $logs    日志信息
     * @return bool
     */
    protected function saveChannel(string $channel, array $logs = []): bool
    {
        // 日志处理
        $processors = $this->processor[$channel] ?? $this->processor['*'];

        foreach ($processors as $callback) {
            $logs = $callback($logs, $channel, $this);

            if (false === $logs) {
                return false;
            }
        }

        $log = [];

        foreach ($logs as $level => $info) {
            if (!$this->app->isDebug() && 'debug' == $level) {
                continue;
            }

            if (empty($this->config['level']) || in_array($level, $this->config['level'])) {
                $log[$level] = $info;
            }
        }

        return $this->driver($channel)->save($log);
    }

    /**
     * 实时写入日志信息
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

        // 写入日志
        return $this->saveChannel($this->channel, $log);
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
