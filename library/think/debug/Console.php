<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yangweijie <yangweijiester@gmail.com>
// +----------------------------------------------------------------------

namespace think\debug;

use think\Cache;
use think\Config;
use think\Db;
use think\Debug;
use think\Request;
use think\Response;

/**
 * 浏览器调试输出
 */
class Console
{
    protected $config = [
        'trace_tabs' => ['base' => '基本', 'file' => '文件', 'info' => '流程', 'notice|error' => '错误', 'sql' => 'SQL', 'debug|log' => '调试'],
    ];

    // 实例化并传入参数
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 调试输出接口
     * @access public
     * @param Response  $response Response对象
     * @param array     $log 日志信息
     * @return bool
     */
    public function output(Response $response, array $log = [])
    {
        $request     = Request::instance();
        $contentType = $response->getHeader('Content-Type');
        $accept      = $request->header('accept');
        if (strpos($accept, 'application/json') === 0 || $request->isAjax()) {
            return false;
        } elseif (!empty($contentType) && strpos($contentType, 'html') === false) {
            return false;
        }
        // 获取基本信息
        $runtime = number_format(microtime(true), 8, '.', '') - THINK_START_TIME;
        $reqs    = number_format(1 / $runtime, 2);
        $mem     = number_format((memory_get_usage() - THINK_START_MEM) / 1024, 2);

        if (isset($_SERVER['HTTP_HOST'])) {
            $uri = $_SERVER['SERVER_PROTOCOL'] . ' ' . $_SERVER['REQUEST_METHOD'] . ' : ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } else {
            $uri = 'cmd:' . implode(' ', $_SERVER['argv']);
        }

        // 页面Trace信息
        $base = [
            '请求信息' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' ' . $uri,
            '运行时间' => number_format($runtime, 6) . 's [ 吞吐率：' . $reqs . 'req/s ] 内存消耗：' . $mem . 'kb 文件加载：' . count(get_included_files()),
            '查询信息' => Db::$queryTimes . ' queries ' . Db::$executeTimes . ' writes ',
            '缓存信息' => Cache::$readTimes . ' reads,' . Cache::$writeTimes . ' writes',
            '配置加载' => count(Config::get()),
        ];

        if (session_id()) {
            $base['会话信息'] = 'SESSION_ID=' . session_id();
        }

        $info = Debug::getFile(true);

        // 页面Trace信息
        $trace = [];
        foreach ($this->config['trace_tabs'] as $name => $title) {
            $name = strtolower($name);
            switch ($name) {
                case 'base': // 基本信息
                    $trace[$title] = $base;
                    break;
                case 'file': // 文件信息
                    $trace[$title] = $info;
                    break;
                default: // 调试信息
                    if (strpos($name, '|')) {
                        // 多组信息
                        $names  = explode('|', $name);
                        $result = [];
                        foreach ($names as $name) {
                            $result = array_merge($result, isset($log[$name]) ? $log[$name] : []);
                        }
                        $trace[$title] = $result;
                    } else {
                        $trace[$title] = isset($log[$name]) ? $log[$name] : '';
                    }
            }
        }

        //输出到控制台
        $lines = '';
        foreach ($trace as $type => $msg) {
            $lines .= $this->console($type, $msg);
        }
        $js = <<<JS

<script type='text/javascript'>
{$lines}
</script>
JS;
        return $js;
    }

    protected function console($type, $msg)
    {
        $type       = strtolower($type);
        $trace_tabs = array_values($this->config['trace_tabs']);
        $line[]     = ($type == $trace_tabs[0] || '调试' == $type || '错误' == $type)
        ? "console.group('{$type}');"
        : "console.groupCollapsed('{$type}');";

        foreach ((array) $msg as $key => $m) {
            switch ($type) {
                case '调试':
                    $var_type = gettype($m);
                    if (in_array($var_type, ['array', 'string'])) {
                        $line[] = "console.log(" . json_encode($m) . ");";
                    } else {
                        $line[] = "console.log(" . json_encode(var_export($m, 1)) . ");";
                    }
                    break;
                case '错误':
                    $msg    = str_replace("\n", '\n', $m);
                    $style  = 'color:#F4006B;font-size:14px;';
                    $line[] = "console.error(\"%c{$msg}\", \"{$style}\");";
                    break;
                case 'sql':
                    $msg    = str_replace("\n", '\n', $m);
                    $style  = "color:#009bb4;";
                    $line[] = "console.log(\"%c{$msg}\", \"{$style}\");";
                    break;
                default:
                    $m      = is_string($key) ? $key . ' ' . $m : $key + 1 . ' ' . $m;
                    $msg    = json_encode($m);
                    $line[] = "console.log({$msg});";
                    break;
            }
        }
        $line[] = "console.groupEnd();";
        return implode(PHP_EOL, $line);
    }

}
