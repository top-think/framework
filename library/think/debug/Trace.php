<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\debug;

use think\Config;
use think\Log;
use think\Request;
use think\Response;

class Trace
{
    public static function inject(Response $response)
    {
        $config = Config::get('trace');

        $type = isset($config['type']) ? $config['type'] : 'Html';

        if ($type !== false && !Request::instance()->isAjax() && $response->getContentType() == 'text/html') {

            $class = false !== strpos($type, '\\') ? $type : '\\think\\debug\\trace\\' . ucwords($type);

            unset($config['type']);
            $trace = new $class($config);

            $output = $trace->output(Log::getLog());

            $content = $response->getContent();

            $pos = strripos($content, '</body>');
            if (false !== $pos) {
                $content = substr($content, 0, $pos) . $output . substr($content, $pos);
            } else {
                $content = $content . $output;
            }

            $response->content($content);
        }
    }
}