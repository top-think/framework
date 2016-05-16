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

class Jsonp extends Response
{
    // 输出参数
    protected $options = [
        'var_jsonp_handler'     => 'callback',
        'default_jsonp_handler' => 'jsonpReturn',
        'json_encode_param'     => JSON_UNESCAPED_UNICODE,
    ];
    protected $contentType = 'application/javascript';

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        // 返回JSON数据格式到客户端 包含状态信息
        $handler = !empty($_GET[$this->options['var_jsonp_handler']]) ? $_GET[$this->options['var_jsonp_handler']] : $this->options['default_jsonp_handler'];
        $data    = $handler . '(' . json_encode($data, $this->options['json_encode_param']) . ');';
        return $data;
    }

}
