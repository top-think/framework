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

use think\model\Collection as ModelCollection;
use think\response\Redirect;

class Debug
{
    /**
     * 区间时间信息
     * @var array
     */
    protected $info = [];

    /**
     * 区间内存信息
     * @var array
     */
    protected $mem = [];

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(App $app, array $config = [])
    {
        $this->app    = $app;
        $this->config = $config;
    }

    public static function __make(App $app, Config $config)
    {
        return new static($app, $config->get('trace'));
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 记录时间（微秒）和内存使用情况
     * @access public
     * @param  string    $name 标记位置
     * @param  mixed     $value 标记值 留空则取当前 time 表示仅记录时间 否则同时记录时间和内存
     * @return void
     */
    public function remark(string $name, $value = ''): void
    {
        // 记录时间和内存使用
        $this->info[$name] = is_float($value) ? $value : microtime(true);

        if ('time' != $value) {
            $this->mem['mem'][$name]  = is_float($value) ? $value : memory_get_usage();
            $this->mem['peak'][$name] = memory_get_peak_usage();
        }
    }

    /**
     * 统计某个区间的时间（微秒）使用情况
     * @access public
     * @param  string            $start 开始标签
     * @param  string            $end 结束标签
     * @param  integer|string    $dec 小数位
     * @return string
     */
    public function getRangeTime(string $start, string $end, int $dec = 6): string
    {
        if (!isset($this->info[$end])) {
            $this->info[$end] = microtime(true);
        }

        return number_format(($this->info[$end] - $this->info[$start]), $dec);
    }

    /**
     * 统计从开始到统计时的时间（微秒）使用情况
     * @access public
     * @param  integer|string $dec 小数位
     * @return string
     */
    public function getUseTime(int $dec = 6): string
    {
        return number_format((microtime(true) - $this->app->getBeginTime()), $dec);
    }

    /**
     * 获取当前访问的吞吐率情况
     * @access public
     * @return string
     */
    public function getThroughputRate(): string
    {
        return number_format(1 / $this->getUseTime(), 2) . 'req/s';
    }

    /**
     * 记录区间的内存使用情况
     * @access public
     * @param  string            $start 开始标签
     * @param  string            $end 结束标签
     * @param  integer|string    $dec 小数位
     * @return string
     */
    public function getRangeMem(string $start, string $end, int $dec = 2): string
    {
        if (!isset($this->mem['mem'][$end])) {
            $this->mem['mem'][$end] = memory_get_usage();
        }

        $size = $this->mem['mem'][$end] - $this->mem['mem'][$start];
        $a    = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pos  = 0;

        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }

        return round($size, $dec) . " " . $a[$pos];
    }

    /**
     * 统计从开始到统计时的内存使用情况
     * @access public
     * @param  integer|string $dec 小数位
     * @return string
     */
    public function getUseMem(int $dec = 2): string
    {
        $size = memory_get_usage() - $this->app->getBeginMem();
        $a    = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pos  = 0;

        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }

        return round($size, $dec) . " " . $a[$pos];
    }

    /**
     * 统计区间的内存峰值情况
     * @access public
     * @param  string            $start 开始标签
     * @param  string            $end 结束标签
     * @param  integer|string    $dec 小数位
     * @return string
     */
    public function getMemPeak(string $start, string $end, int $dec = 2): string
    {
        if (!isset($this->mem['peak'][$end])) {
            $this->mem['peak'][$end] = memory_get_peak_usage();
        }

        $size = $this->mem['peak'][$end] - $this->mem['peak'][$start];
        $a    = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pos  = 0;

        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }

        return round($size, $dec) . " " . $a[$pos];
    }

    /**
     * 获取文件加载信息
     * @access public
     * @param  bool  $detail 是否显示详细
     * @return integer|array
     */
    public function getFile(bool $detail = false)
    {
        if ($detail) {
            $files = get_included_files();
            $info  = [];

            foreach ($files as $key => $file) {
                $info[] = $file . ' ( ' . number_format(filesize($file) / 1024, 2) . ' KB )';
            }

            return $info;
        }

        return count(get_included_files());
    }

    /**
     * 浏览器友好的变量输出
     * @access public
     * @param  mixed         $var 变量
     * @param  boolean       $echo 是否输出 默认为true 如果为false 则返回输出字符串
     * @param  string        $label 标签 默认为空
     * @return void|string
     */
    public function dump($var, bool $echo = true, string $label = null)
    {
        $label = (null === $label) ? '' : rtrim($label) . ':';
        if ($var instanceof Model || $var instanceof ModelCollection) {
            $var = $var->toArray();
        }

        ob_start();
        var_dump($var);

        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

        if (PHP_SAPI == 'cli') {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, ENT_SUBSTITUTE);
            }
            $output = '<pre>' . $label . $output . '</pre>';
        }
        if ($echo) {
            echo($output);
            return;
        }
        return $output;
    }

    public function inject(Response $response, &$content)
    {
        $config = $this->config;
        $type   = $config['type'] ?? 'Html';

        unset($config['type']);

        $trace = App::factory($type, '\\think\\debug\\', $config);

        if ($response instanceof Redirect) {
            //TODO 记录
        } else {
            $output = $trace->output($response, $this->app['log']->getLog());
            if (is_string($output)) {
                // trace调试信息注入
                $pos = strripos($content, '</body>');
                if (false !== $pos) {
                    $content = substr($content, 0, $pos) . $output . substr($content, $pos);
                } else {
                    $content = $content . $output;
                }
            }
        }
    }
}
