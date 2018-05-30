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

namespace think\log\driver;

use think\App;

/**
 * 本地化调试输出到文件
 */
class File
{
    protected $config = [
        'time_format' => ' c ',
        'single'      => false,
        'file_size'   => 2097152,
        'path'        => '',
        'apart_level' => [],
        'max_files'   => 0,
        'json'        => false,
    ];

    protected $app;

    // 实例化并传入参数
    public function __construct(App $app, $config = [])
    {
        $this->app = $app;

        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        if (empty($this->config['path'])) {
            $this->config['path'] = $this->app->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }
    }

    /**
     * 日志写入接口
     * @access public
     * @param  array    $log    日志信息
     * @param  bool     $append 是否追加请求信息
     * @return bool
     */
    public function save(array $log = [], $append = false)
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

                if ($this->config['json']) {
                    $info[$type][] = $msg;
                } else {
                    $info[$type][] = '[ ' . $type . ' ]' . $msg;
                }
            }

            if (!$this->config['json'] && in_array($type, $this->config['apart_level'])) {
                // 独立记录的日志级别
                $filename = $this->getApartLevelFile($path, $type);

                $this->write($info[$type], $filename, true, $append);

                unset($info[$type]);
            }
        }

        if ($info) {
            return $this->write($info, $destination, false, $append);
        }

        return true;
    }

    /**
     * 日志写入
     * @access protected
     * @param  array     $message 日志信息
     * @param  string    $destination 日志文件
     * @param  bool      $apart 是否独立文件写入
     * @param  bool      $append 是否追加请求信息
     * @return bool
     */
    protected function write($message, $destination, $apart = false, $append = false)
    {
        // 检测日志文件大小，超过配置大小则备份日志文件重新生成
        $this->checkLogSize($destination);

        if (PHP_SAPI == 'cli') {
            $message = $this->parseCliMessage($message);
        } elseif ($this->config['json']) {
            $message = $this->parseJsonMessage($message, $append);
        } else {
            $message = $this->parseWebMessage($message, $append, $apart);
        }

        return error_log($message, 3, $destination);
    }

    /**
     * 获取主日志文件名
     * @access public
     * @return string
     */
    protected function getMasterLogFile()
    {
        if ($this->config['single']) {
            $name = is_string($this->config['single']) ? $this->config['single'] : 'single';

            $destination = $this->config['path'] . $name . '.log';
        } else {
            $cli = PHP_SAPI == 'cli' ? '_cli' : '';

            if ($this->config['max_files']) {
                $filename = date('Ymd') . $cli . '.log';
                $files    = glob($this->config['path'] . '*.log');

                try {
                    if (count($files) > $this->config['max_files']) {
                        unlink($files[0]);
                    }
                } catch (\Exception $e) {
                }
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
    protected function getApartLevelFile($path, $type)
    {
        $cli = PHP_SAPI == 'cli' ? '_cli' : '';

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
     * @param  string    $destination 日志文件
     * @return void
     */
    protected function checkLogSize($destination)
    {
        if (is_file($destination) && floor($this->config['file_size']) <= filesize($destination)) {
            try {
                rename($destination, dirname($destination) . DIRECTORY_SEPARATOR . time() . '-' . basename($destination));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * JSON日志解析
     * @access protected
     * @param  array     $message 日志信息
     * @param  bool      $append 是否追加请求信息
     * @return string
     */
    protected function parseJsonMessage($message, $append)
    {
        if ($this->app->isDebug() && $append) {
            // 获取基本信息
            $runtime = round(microtime(true) - $this->app->getBeginTime(), 10);
            $reqs    = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';

            $memory_use = number_format((memory_get_usage() - $this->app->getBeginMem()) / 1024, 2);

            $info = [
                'host'   => $this->app['request']->host(),
                'time'   => number_format($runtime, 6) . 's',
                'reqs'   => $reqs . 'req/s',
                'memory' => $memory_use . 'kb',
                'file'   => count(get_included_files()),
            ];
        }

        $this->appendJsonRequestLog($info);

        foreach ($message as $type => $msg) {
            $info[$type] = implode("\r\n", $msg);
        }

        return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n";
    }

    /**
     * WEB日志解析
     * @access protected
     * @param  array    $message 日志信息
     * @param  bool     $append 是否追加请求信息
     * @param  bool     $apart  是否独立日志
     * @return string
     */
    protected function parseWebMessage($message, $append, $apart)
    {
        if ($this->app->isDebug() && $append && !$apart) {
            // 增加额外的调试信息
            $runtime = round(microtime(true) - $this->app->getBeginTime(), 10);
            $reqs    = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';

            $memory_use = number_format((memory_get_usage() - $this->app->getBeginMem()) / 1024, 2);

            $time_str   = '[运行时间：' . number_format($runtime, 6) . 's] [吞吐率：' . $reqs . 'req/s]';
            $memory_str = ' [内存消耗：' . $memory_use . 'kb]';
            $file_load  = ' [文件加载：' . count(get_included_files()) . ']';

            if (isset($message['info'])) {
                array_unshift($message['info'], $time_str . $memory_str . $file_load);
            } else {
                $message['info'][] = $time_str . $memory_str . $file_load;
            }
        }

        $this->appendRequestLog($message, $apart);

        foreach ($message as $type => $msg) {
            if (is_array($msg)) {
                $info[] = implode("\r\n", $msg);
            } else {
                $info[] = $msg;
            }
        }

        return implode("\r\n", $info) . "\r\n";
    }

    /**
     * CLI日志解析
     * @access protected
     * @param  array     $message 日志信息
     * @return string
     */
    protected function parseCliMessage($message)
    {
        $now = date($this->config['time_format']);

        if ($this->config['json']) {
            $info['timestamp'] = $now;

            foreach ($message as $type => $msg) {
                $info[$type] = implode("\r\n", $msg);
            }

            $message = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n";
        } else {
            foreach ($message as $type => $msg) {
                $info[] = implode("\r\n", $msg);
            }

            $message = implode("\r\n", $info);

            $message = "[{$now}]" . $message . "\r\n";
        }

        return $message;
    }

    /**
     * 追加JSON请求日志
     * @access protected
     * @param  array     $info 日志信息
     * @return void
     */
    protected function appendJsonRequestLog(&$info)
    {
        $info['timestamp'] = date($this->config['time_format']);
        $info['ip']        = $this->app['request']->ip();
        $info['method']    = $this->app['request']->method();
        $info['uri']       = $this->app['request']->url();
    }

    /**
     * 追加请求日志
     * @access protected
     * @param  array     $message 日志信息
     * @param  bool      $apart   独立日志
     * @return void
     */
    protected function appendRequestLog(&$message, $apart = false)
    {
        $now    = date($this->config['time_format']);
        $ip     = $this->app['request']->ip();
        $method = $this->app['request']->method();
        $uri    = $this->app['request']->url(true);

        if ($apart) {
            array_unshift($message, "---------------------------------------------------------------\r\n[{$now}] {$ip} {$method} {$uri}");

        } else {
            if (!isset($message['info'])) {
                $message['info'] = [];
            }

            array_unshift($message['info'], "---------------------------------------------------------------\r\n[{$now}] {$ip} {$method} {$uri}");
        }
    }
}
