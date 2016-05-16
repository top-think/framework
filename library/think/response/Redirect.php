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

    protected $options = [];

    // URL参数
    protected $params = [];

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        $this->isExit             = true;
        $url                      = preg_match('/^(https?:|\/)/', $data) ? $data : Url::build($data, $this->params);
        $this->header['Location'] = $url;
        $this->header['status']   = isset($this->header['status']) ? $this->header['status'] : 301;
        return;
    }

    public function params($params = [])
    {
        $this->params = $params;
        return $this;
    }
}
