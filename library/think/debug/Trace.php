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
use think\exception\ClassNotFoundException;
use think\Log;
use think\Request;
use think\Response;
use think\response\Redirect;

class Trace
{
    public static function inject(Response $response)
    {
        $config = Config::get('trace');

        $type = isset($config['type']) ? $config['type'] : 'Html';

        if ($type !== false) {
            $request     = Request::instance();
            $accept      = $request->header('accept');
            $contentType = $response->getHeader('Content-Type');

            $class = false !== strpos($type, '\\') ? $type : '\\think\\debug\\trace\\' . ucwords($type);
            unset($config['type']);
            if(class_exists($class)) {
                $trace = new $class($config);
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }

            if ($response instanceof Redirect) {
                //TODO 记录
            } elseif (strpos($accept, 'application/json') === 0 || $request->isAjax()) {
                //TODO 记录
            } elseif (!empty($contentType) && strpos($contentType, 'html') === false) {
                //TODO 记录
            } else {
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
}