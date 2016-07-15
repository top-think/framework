<?php

/**
 * 用法：
 * load_trait('controller/Jump');
 * class index
 * {
 *     use \traits\controller\Jump;
 *     public function index(){
 *         $this->error();
 *         $this->redirect();
 *     }
 * }
 */
namespace traits\controller;

use think\Config;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use think\response\Redirect;
use think\Url;
use think\View as ViewTemplate;

trait Jump
{
    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param integer $wait 跳转等待时间
     * @return array
     */
    protected function success($msg = '', $url = null, $data = '', $wait = 3)
    {
        $code = 1;
        if (is_numeric($msg)) {
            $code = $msg;
            $msg  = '';
        }
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } else {
            $url = preg_match('/^(https?:|\/)/', $url) ? $url : Url::build($url);
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            $result = ViewTemplate::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_success_tmpl'), $result);
        }
        return Response::create($result, $type);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param integer $wait 跳转等待时间
     * @return void
     */
    protected function error($msg = '', $url = null, $data = '', $wait = 3)
    {
        $code = 0;
        if (is_numeric($msg)) {
            $code = $msg;
            $msg  = '';
        }
        if (is_null($url)) {
            $url = 'javascript:history.back(-1);';
        } else {
            $url = preg_match('/^(https?:|\/)/', $url) ? $url : Url::build($url);
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            $result = ViewTemplate::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), $result);
        }
        $response = Response::create($result, $type);
        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param integer $code 返回的code
     * @param mixed $msg 提示信息
     * @param string $type 返回数据格式
     * @return mixed
     */
    protected function result($data, $code = 0, $msg = '', $type = '')
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => $_SERVER['REQUEST_TIME'],
            'data' => $data,
        ];
        $type = $type ?: $this->getResponseType();
        return Response::create($result, $type);
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param array|integer $params 其它URL参数
     * @param integer $code http code
     * @return void
     */
    protected function redirect($url, $params = [], $code = 302)
    {
        $response = new Redirect($url);
        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }
        $response->code($code)->params($params);
        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        $isAjax = Request::instance()->isAjax();
        return $isAjax ? Config::get('default_ajax_return') : Config::get('default_return_type');
    }
}
