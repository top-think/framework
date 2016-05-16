<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\response;

use think\Response;
use think\Url;

class Redirect extends Response
{

    protected $options = [
        'http_response_code' => 301,
        'http_url_params'    => [],
    ];

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        $this->isExit             = true;
        $url                      = preg_match('/^(https?:|\/)/', $data) ? $data : Url::build($data, $this->options['http_url_params']);
        $this->header['Location'] = $url;
        $this->header['status']   = $this->options['http_response_code'];
        return;
    }

}
