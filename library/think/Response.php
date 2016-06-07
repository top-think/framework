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

use think\Hook;
use think\Request;

class Response
{
    // 输出类型的实例化对象
    protected static $instance = [];
    // 输出数据的转换方法
    protected $transform;
    // 输出数据
    protected $data;
    // 输出类型
    protected $type;
    // 当前的contentType
    protected $contentType;
    // 可用的输出类型
    protected $contentTypes = [
        'json'   => 'application/json',
        'xml'    => 'text/xml',
        'html'   => 'text/html',
        'jsonp'  => 'application/javascript',
        'script' => 'application/javascript',
        'text'   => 'text/plain',
    ];

    // 输出参数
    protected $options = [];
    // header参数
    protected $header = [];

    /**
     * 架构函数
     * @access public
     * @param mixed $data 输出数据
     * @param string $type 输出类型
     * @param array $options 输出参数
     */
    public function __construct($data = [], $type = '', $options = [])
    {
        $this->data = $data;
        if (empty($type)) {
            $isAjax = Request::instance()->isAjax();
            $type   = $isAjax ? 'json' : 'html';
        }
        $this->type = strtolower($type);

        if (isset($this->contentTypes[$this->type])) {
            $this->contentType($this->contentTypes[$this->type]);
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        // 方便获取某个类型的实例
        self::$instance[$this->type] = $this;
    }

    /**
     * 创建Response对象
     * @access public
     * @param mixed $data 输出数据
     * @param string $type 输出类型
     * @param array $options 输出参数
     * @return Response
     */
    public static function create($data = [], $type = '', $options = [])
    {
        if (empty($type)) {
            $isAjax = Request::instance()->isAjax();
            $type   = $isAjax ? 'json' : 'html';
        }
        $type = strtolower($type);

        if (!isset(self::$instance[$type])) {
            $class = (isset($options['namespace']) ? $options['namespace'] : '\\think\\response\\') . ucfirst($type);
            if (class_exists($class)) {
                $response = new $class($data, $type, $options);
            } else {
                $response = new static($data, $type, $options);
            }
            self::$instance[$type] = $response;
        }
        return self::$instance[$type];
    }

    /**
     * 发送数据到客户端
     * @access public
     * @param mixed $data 数据
     * @return mixed
     * @throws Exception
     */
    public function send($data = null)
    {
        $data = !is_null($data) ? $data : $this->data;

        if (isset($this->contentType)) {
            $this->contentType($this->contentType);
        }

        if (is_callable($this->transform)) {
            $data = call_user_func_array($this->transform, [$data]);
        }

        defined('RESPONSE_TYPE') or define('RESPONSE_TYPE', $this->type);

        // 处理输出数据
        $data = $this->output($data);

        // 监听response_data
        Hook::listen('response_data', $data, $this);

        // 发送头部信息
        if (!headers_sent() && !empty($this->header)) {
            // 发送状态码
            if (isset($this->header['status'])) {
                http_response_code($this->header['status']);
                unset($this->header['status']);
            }

            foreach ($this->header as $name => $val) {
                header($name . ':' . $val);
            }
        }
        if (is_scalar($data)) {
            echo $data;
        } elseif (!is_null($data)) {
            throw new Exception('不支持的数据类型输出：' . gettype($data));
        }

        if (function_exists('fastcgi_finish_request')) {
            // 提高页面响应
            fastcgi_finish_request();
        }
        return $data;
    }

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        return $data;
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
     * 输出的参数
     * @access public
     * @param mixed $options 输出参数
     * @return $this
     */
    public function options($options = [])
    {
        $this->options = array_merge($this->options, $options);
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
     * 返回封装后的API数据到客户端
     * @access public
     * @param mixed $data 要返回的数据
     * @param integer $code 返回的code
     * @param string $msg 提示信息
     * @return mixed
     */
    public function result($data, $code = 0, $msg = '')
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => $_SERVER['REQUEST_TIME'],
            'data' => $data,
        ];
        return $this->data($result);
    }

    /**
     * 设置响应头
     * @access public
     * @param string|array $name 参数名
     * @param string $value 参数值
     * @return $this
     */
    public function header($name, $value = null)
    {
        if (is_array($name)) {
            $this->header = array_merge($this->header, $name);
        } else {
            $this->header[$name] = $value;
        }
        return $this;
    }

    /**
     * 发送HTTP Location
     * @param string $url Location地址
     * @return $this
     */
    public function location($url)
    {
        $this->header['Location'] = $url;
        return $this;
    }

    /**
     * 发送HTTP状态
     * @param integer $code 状态码
     * @return $this
     */
    public function code($code)
    {
        $this->header['status'] = $code;
        return $this;
    }

    /**
     * LastModified
     * @param string $time
     * @return $this
     */
    public function lastModified($time)
    {
        $this->header['Last-Modified'] = $time;
        return $this;
    }

    /**
     * Expires
     * @param string $time
     * @return $this
     */
    public function expires($time)
    {
        $this->header['Expires'] = $time;
        return $this;
    }

    /**
     * ETag
     * @param string $etag
     * @return $this
     */
    public function eTag($etag)
    {
        $this->header['etag'] = $etag;
        return $this;
    }

    /**
     * 页面缓存控制
     * @param string $cache 状态码
     * @return $this
     */
    public function cacheControl($cache)
    {
        $this->header['Cache-control'] = $cache;
        return $this;
    }

    /**
     * 页面输出类型
     * @param string $contentType 输出类型
     * @param string $charset 输出编码
     * @return $this
     */
    public function contentType($contentType, $charset = 'utf-8')
    {
        $this->header['Content-Type'] = $contentType . '; charset=' . $charset;
        return $this;
    }

    /**
     * 获取头部信息
     * @param string $name 头部名称
     * @return mixed
     */
    public function getHeader($name = '')
    {
        return !empty($name) ? $this->header[$name] : $this->header;
    }

    /**
     * 获取数据
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 获取输出类型
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
