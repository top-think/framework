<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\log\driver;

use think\App;
use think\contract\LogHandlerInterface;

/**
 * 本地化调试输出到文件
 */
class File implements LogHandlerInterface
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'time_format' => 'c',
        'single'      => false,
        'file_size'   => 2097152,
        'path'        => '',
        'apart_level' => [],
        'max_files'   => 0,
        'json'        => false,
    ];

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 是否控制台执行
     * @var bool
     */
    protected $isCli = false;

    // 实例化并传入参数
    public function __construct(App $app, $config = [])
    {
        $this->app = $app;

        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $this->isCli = $app->runningInConsole();
    }

    /**
     * 日志写入接口
     * @access public
     * @param  array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        $destination = $this->getMasterLogFile();

        $path = dirname($destination);
        !is_dir($path) && mkdir($path, 0755, true);

        $info = [];

        foreach ($log as $type => $val) {

            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }

                $info[$type][] = $this->config['json'] ? $msg : '[ ' . $type . ' ] ' . $msg;
            }

            if (!$this->config['json'] && (true === $this->config['apart_level'] || in_array($type, $this->config['apart_level']))) {
                // 独立记录的日志级别
                $filename = $this->getApartLevelFile($path, $type);

                $this->write($info[$type], $filename, true);

                unset($info[$type]);
            }
        }

        if ($info) {
            return $this->write($info, $destination, false);
        }

        return true;
    }

    /**
     * 日志写入
     * @access protected
     * @param  array  $message 日志信息
     * @param  string $destination 日志文件
     * @param  bool   $apart 是否独立文件写入
     * @return bool
     */
    protected function write(array $message, string $destination, bool $apart = false): bool
    {
        // 检测日志文件大小，超过配置大小则备份日志文件重新生成
        $this->checkLogSize($destination);

        $info = [];
        // 日志信息封装
        $info['timestamp'] = date($this->config['time_format']);

        foreach ($message as $type => $msg) {
            $info[$type] = is_array($msg) ? implode(PHP_EOL, $msg) : $msg;
        }

        if ($this->isCli) {
            $message = $this->parseCliLog($info);
        } else {
            $message = $this->parseLog($info);
        }

        return error_log($message, 3, $destination);
    }

    /**
     * 获取主日志文件名
     * @access public
     * @return string
     */
    protected function getMasterLogFile(): string
    {
        if (empty($this->config['path'])) {
            $this->config['path'] = $this->app->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }

        if ($this->config['max_files']) {
            $files = glob($this->config['path'] . '*.log');

            try {
                if (count($files) > $this->config['max_files']) {
                    unlink($files[0]);
                }
            } catch (\Exception $e) {
                //
            }
        }

        if ($this->config['single']) {
            $name        = is_string($this->config['single']) ? $this->config['single'] : 'single';
            $cli         = $this->isCli ? '_cli' : '';
            $destination = $this->config['path'] . $name . $cli . '.log';
        } else {
            $cli = $this->isCli ? '_cli' : '';

            if ($this->config['max_files']) {
                $filename = date('Ymd') . $cli . '.log';
            } else {
                $filename = date('Ym') . DIRECTORY_SEPARATOR . date('d') . $cli . '.log';
            }

            $destination = $this->config['path'] . $filename;
        }

        return $destination;
    }

    /**
     * 获取独立日志文件名
     * @access public
     * @param  string $path 日志目录
     * @param  string $type 日志类型
     * @return string
     */
    protected function getApartLevelFile(string $path, string $type): string
    {
        $cli = $this->isCli ? '_cli' : '';

        if ($this->config['single']) {
            $name = is_string($this->config['single']) ? $this->config['single'] : 'single';

            $name .= '_' . $type;
        } elseif ($this->config['max_files']) {
            $name = date('Ymd') . '_' . $type . $cli;
        } else {
            $name = date('d') . '_' . $type . $cli;
        }

        return $path . DIRECTORY_SEPARATOR . $name . '.log';
    }

    /**
     * 检查日志文件大小并自动生成备份文件
     * @access protected
     * @param  string $destination 日志文件
     * @return void
     */
    protected function checkLogSize(string $destination): void
    {
        if (is_file($destination) && floor($this->config['file_size']) <= filesize($destination)) {
            try {
                rename($destination, dirname($destination) . DIRECTORY_SEPARATOR . time() . '-' . basename($destination));
            } catch (\Exception $e) {
                //
            }
        }
    }

    /**
     * CLI日志解析
     * @access protected
     * @param  array $info 日志信息
     * @return string
     */
    protected function parseCliLog(array $info): string
    {
        if ($this->config['json']) {
            $message = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            $now = $info['timestamp'];
            unset($info['timestamp']);

            $message = implode(PHP_EOL, $info);

            $message = "[{$now}]" . $message . PHP_EOL;
        }

        return $message;
    }

    /**
     * 解析日志
     * @access protected
     * @param  array $info 日志信息
     * @return string
     */
    protected function parseLog(array $info): string
    {
        $requestInfo = [
            'ip'     => $this->app->request->ip(),
            'method' => $this->app->request->method(),
            'host'   => $this->app->request->host(),
            'uri'    => $this->app->request->url(),
        ];

        if ($this->config['json']) {
            $info = $requestInfo + $info;
            return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        array_unshift($info, "---------------------------------------------------------------" . PHP_EOL . "[{$info['timestamp']}] {$requestInfo['ip']} {$requestInfo['method']} {$requestInfo['host']}{$requestInfo['uri']}");
        unset($info['timestamp']);

        return implode(PHP_EOL, $info) . PHP_EOL;
    }

}
