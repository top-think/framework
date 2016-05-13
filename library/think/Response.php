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

namespace think;

use think\Config;
use think\Url;

class Response
{
    protected static $instance;
    // 输出数据的转换方法
    protected $transform = null;

    // 输出数据
    protected $data = '';
    // 是否exit
    protected $isExit = false;
    // 输出类型
    protected $type = '';
    // contentType
    protected $contentType = [
        'json'   => 'application/json',
        'xml'    => 'text/xml',
        'html'   => 'text/html',
        'jsonp'  => 'application/javascript',
        'script' => 'application/javascript',
        'text'   => 'text/plain',
    ];

    /**
     * 架构函数
     * @access public
     * @param array $options 参数
     */
    public function __construct($type = '')
    {
        $this->type = $type;
    }

    /**
     * 初始化
     * @access public
     * @param string $type 输出类型
     * @return \think\Response
     */
    public static function instance($type = '')
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($type);
        }
        return self::$instance;
    }

    /**
     * 发送数据到客户端
     * @access public
     * @param mixed $data 数据
     * @param string $type 返回类型
     * @param bool $return 是否返回数据
     * @return mixed
     */
    public function send($data = [], $type = '', $return = false)
    {
        if ('' == $type) {
            $type = $this->type ?: (IS_AJAX ? Config::get('default_ajax_return') : Config::get('default_return_type'));
        }
        $type = strtolower($type);
        $data = $data ?: $this->data;

        if (!headers_sent() && isset($this->contentType[$type])) {
            header('Content-Type:' . $this->contentType[$type] . '; charset=utf-8');
        }

        if (is_callable($this->transform)) {
            $data = call_user_func_array($this->transform, [$data]);
        } else {
            switch ($type) {
                case 'json':
                    // 返回JSON数据格式到客户端 包含状态信息
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                    break;
                case 'jsonp':
                    // 返回JSON数据格式到客户端 包含状态信息
                    $handler = !empty($_GET[Config::get('var_jsonp_handler')]) ? $_GET[Config::get('var_jsonp_handler')] : Config::get('default_jsonp_handler');
                    $data    = $handler . '(' . json_encode($data, JSON_UNESCAPED_UNICODE) . ');';
                    break;
            }
        }

        APP_HOOK && Hook::listen('return_data', $data);

        if ($return) {
            return $data;
        }

        echo $data;
        $this->isExit() && exit();
    }

    /**
     * 转换控制器输出的数据
     * @access public
     * @param mixed $callback 调用的转换方法
     * @return $this
     */
    public function transform($callback)
    {
        $this->transform = $callback;
        return $this;
    }

    /**
     * 输出数据设置
     * @access public
     * @param mixed $data 输出数据
     * @return $this
     */
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 输出类型设置
     * @access public
     * @param string $type 输出内容的格式类型
     * @return $this
     */
    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 输出是否exit设置
     * @access public
     * @param bool $exit 是否退出
     * @return $this
     */
    public function isExit($exit = null)
    {
        if (is_null($exit)) {
            return $this->isExit;
        }
        $this->isExit = (boolean) $exit;
        return $this;
    }

    /**
     * 返回封装后的API数据到客户端
     * @access public
     * @param mixed $data 要返回的数据
     * @param integer $code 返回的code
     * @param string $msg 提示信息
     * @return mixed
     */
    public function result($data, $code = 0, $msg = '', $type = '')
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => NOW_TIME,
            'data' => $data,
        ];
        $this->type = $type;
        return $result;
    }

    /**
     * URL重定向
     * @access public
     * @param string $url 跳转的URL表达式
     * @param array|int $params 其它URL参数或http code
     * @return void
     */
    public function redirect($url, $params = [])
    {
        $http_response_code = 301;
        if (is_int($params) && in_array($params, [301, 302])) {
            $http_response_code = $params;
            $params             = [];
        }
        $url = preg_match('/^(https?:|\/)/', $url) ? $url : Url::build($url, $params);
        header('Location: ' . $url, true, $http_response_code);
    }

    /**
     * 设置响应头
     * @access public
     * @param string $name 参数名
     * @param string $value 参数值
     * @return $this
     */
    public function header($name, $value)
    {
        header($name . ':' . $value);
        return $this;
    }

    /**
     * 发送HTTP状态
     * @param integer $code 状态码
     * @return $this
     */
    public function code($code)
    {
        http_response_code($code);
        return $this;
    }
}
