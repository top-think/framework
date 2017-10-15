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

namespace think\response;

use think\Container;
use think\Response;

class Jump extends Response
{
    protected $contentType = 'text/html';

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     * @throws \Exception
     */
    protected function output($data)
    {
        $config = Container::get('config');
        $data   = Container::get('view')
            ->init($config->pull('template'), $config->get('view_replace_str'))
            ->fetch($config->get('dispatch_error_tmpl'), $data);
        return $data;
    }
}
