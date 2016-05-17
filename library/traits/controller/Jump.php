<?php

/**
 * 用法：
 * T('controller/Jump');
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
use think\Response;
use think\response\Redirect;
use think\View as ViewTemplate;

trait Jump
{
    /**
     * 操作成功跳转的快捷方法
     * @access public
     * @param mixed $msg 提示信息
     * @param string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param integer $wait 跳转等待时间
     * @return void
     */
    public function success($msg = '', $url = null, $data = '', $wait = 3)
    {
        $code = 1;
        if (is_numeric($msg)) {
            $code = $msg;
            $msg  = '';
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => is_null($url) && isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : $url,
            'wait' => $wait,
        ];

        $type = IS_AJAX ? Config::get('default_ajax_return') : Config::get('default_return_type');
        if ('html' == strtolower($type)) {
            $result = ViewTemplate::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_success_tmpl'), $result);
        }
        return Response::create($result, $type);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access public
     * @param mixed $msg 提示信息
     * @param string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param integer $wait 跳转等待时间
     * @return void
     */
    public function error($msg = '', $url = null, $data = '', $wait = 3)
    {
        $code = 0;
        if (is_numeric($msg)) {
            $code = $msg;
            $msg  = '';
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => is_null($url) ? 'javascript:history.back(-1);' : $url,
            'wait' => $wait,
        ];

        $type = IS_AJAX ? Config::get('default_ajax_return') : Config::get('default_return_type');
        if ('html' == strtolower($type)) {
            $result = ViewTemplate::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch(Config::get('dispatch_error_tmpl'), $result);
        }
        $response = Response::create($result, $type);
        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access public
     * @param mixed $data 要返回的数据
     * @param integer $code 返回的code
     * @param mixed $msg 提示信息
     * @param string $type 返回数据格式
     * @return mixed
     */
    public function result($data, $code = 0, $msg = '', $type = '')
    {
        return Response::create([], $type)->result($data, $code, $msg);
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param integer $code http code
     * @param array $params 其它URL参数
     * @return void
     */
    public function redirect($url, $code = 301, $params = [])
    {
        $response = new Redirect($url);
        $response->code($code)->params($params);
        throw new HttpResponseException($response);
    }

}
