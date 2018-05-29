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

    protected $writed = [];
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
     * @param  array $log 日志信息
     * @return bool
     */
    public function save(array $log = [])
    {
        $destination = $this->getMasterLogFile();

        $info = [];
        foreach ($log as $type => $val) {

            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }

                if ($this->config['json']) {
                    $info[$type][] = str_replace("\n", ' ', $msg);
                } else {
                    $info[$type][] = '[ ' . $type . ' ]' . $msg;
                }
            }

            if (in_array($type, $this->config['apart_level'])) {
                // 独立记录的日志级别
                $filename = $this->getApartLevelFile();

                $this->write($info[$type], $filename, true);
                unset($info[$type]);
            }
        }

        if ($info) {
            return $this->write($info, $destination);
        }

        return true;
    }

    protected function getApartLevelFile()
    {
        $cli = PHP_SAPI == 'cli' ? '_cli' : '';
        if ($this->config['single']) {
            $name     = is_string($this->config['single']) ? $this->config['single'] : 'single';
            $filename = $path . DIRECTORY_SEPARATOR . $name . '_' . $type . '.log';
        } elseif ($this->config['max_files']) {
            $filename = $path . DIRECTORY_SEPARATOR . date('Ymd') . '_' . $type . $cli . '.log';
        } else {
            $filename = $path . DIRECTORY_SEPARATOR . date('d') . '_' . $type . $cli . '.log';
        }
    }

    protected function getMasterLogFile()
    {
        if ($this->config['single']) {
            $name        = is_string($this->config['single']) ? $this->config['single'] : 'single';
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

        $path = dirname($destination);
        !is_dir($path) && mkdir($path, 0755, true);

        return $destination;
    }

    protected function checkLogSize($destination)
    {
        if (is_file($destination) && floor($this->config['file_size']) <= filesize($destination)) {
            try {
                rename($destination, dirname($destination) . DIRECTORY_SEPARATOR . time() . '-' . basename($destination));
            } catch (\Exception $e) {
            }

            $this->writed[$destination] = false;
        }
    }

    /**
     * 日志写入
     * @access protected
     * @param  array     $message 日志信息
     * @param  string    $destination 日志文件
     * @param  bool      $apart 是否独立文件写入
     * @return bool
     */
    protected function write($message, $destination, $apart = false)
    {
        // 检测日志文件大小，超过配置大小则备份日志文件重新生成
        $this->checkLogSize($destination);

        if (empty($this->writed[$destination]) && PHP_SAPI != 'cli') {
            if ($this->app->isDebug() && !$apart) {
                // 获取基本信息
                $current_uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $runtime     = round(microtime(true) - $this->app->getBeginTime(), 10);
                $reqs        = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';

                $memory_use = number_format((memory_get_usage() - $this->app->getBeginMem()) / 1024, 2);

                if ($this->config['json']) {
                    $info = [
                        'host'   => $_SERVER['HTTP_HOST'],
                        'time'   => number_format($runtime, 6) . 's',
                        'reqs'   => $reqs . 'req/s',
                        'memory' => $memory_use . 'kb',
                        'file'   => count(get_included_files()),
                    ];
                } else {
                    $time_str   = ' [运行时间：' . number_format($runtime, 6) . 's][吞吐率：' . $reqs . 'req/s]';
                    $memory_str = ' [内存消耗：' . $memory_use . 'kb]';
                    $file_load  = ' [文件加载：' . count(get_included_files()) . ']';

                    if (isset($message['info'])) {
                        array_unshift($message['info'], $current_uri . $time_str . $memory_str . $file_load);
                    } else {
                        $message['info'][] = $current_uri . $time_str . $memory_str . $file_load;
                    }
                }
            }

            if ($this->config['json']) {
                $info['timestamp'] = date($this->config['time_format']);
                $info['ip']        = $this->app['request']->ip();
                $info['method']    = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
                $info['uri']       = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                foreach ($message as $type => $msg) {
                    $info[$type] = implode(" ", $msg);
                }

                $message = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n";
            } else {
                $now    = date($this->config['time_format']);
                $ip     = $this->app['request']->ip();
                $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
                $uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                array_unshift($message['info'], "---------------------------------------------------------------\r\n[{$now}] {$ip} {$method} {$uri}");
                foreach ($message as $type => $msg) {
                    $info[$type] = implode("\r\n", $msg);
                }
                $message = implode("\r\n", $info);
            }

            $this->writed[$destination] = true;
        }

        if (PHP_SAPI == 'cli') {
            $now     = date($this->config['time_format']);
            $message = "[{$now}]" . $message;
        }

        return error_log($message, 3, $destination);
    }

}
