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

use think\Request;
use think\Response;
use think\Session;
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
        $url                           = preg_match('/^(https?:|\/)/', $data) ? $data : Url::build($data, $this->params);
        $this->header['Location']      = $url;
        $this->header['status']        = isset($this->header['status']) ? $this->header['status'] : 302;
        $this->header['Cache-control'] = 'no-cache,must-revalidate';
        return;
    }

    public function params($params = [])
    {
        $this->params = $params;
        return $this;
    }

    /**
     * 记住当前url后跳转
     */
    public function remember()
    {
        Session::set('redirect_url', Request::instance()->url());
    }

    /**
     * 跳转到上次记住的url
     */
    public function restore()
    {
        if (Session::has('redirect_url')) {
            $this->data = Session::get('redirect_url');
            Session::delete('redirect_url');
        }
    }
}
