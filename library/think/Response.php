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

class Response
{
    // 输出类型的实例化对象
    protected static $instance = [];
    // 输出数据的转换方法
    protected $transform;
    // 输出数据
    protected $data;
    // 是否exit
    protected $isExit = false;
    // contentType
    protected $contentType = [
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
     * @param array $options 参数
     */
    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * 创建一个response对象
     * @access public
     * @param string $type 输出类型
     * @param array $options 参数
     * @return \think\Response
     */
    public static function create($type = '', $options = [])
    {
        $type = strtolower($type ?: (IS_AJAX ? 'json' : 'html'));
        if (!isset(self::$instance[$type])) {
            self::$instance[$type] = new static($options);
            self::$instance[$type]->type($type, $options);
        }
        return self::$instance[$type];
    }

    /**
     * 发送数据到客户端
     * @access public
     * @param mixed $data 数据
     * @return mixed
     */
    public function send($data = [])
    {
        $data = $data ?: $this->data;

        if (is_callable($this->transform)) {
            $data = call_user_func_array($this->transform, [$data]);
        }

        // 处理输出数据
        $data = $this->output($data);
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
        echo $data;
        if ($this->isExit) {
            exit;
        } else {
            return $data;
        }
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
     * 输出类型设置
     * @access public
     * @param string $type 输出内容的格式类型
     * @param array $options 参数
     * @return $this
     */
    public function type($type, $options = [])
    {
        $type = strtolower($type);
        if (!isset(self::$instance[$type])) {
            $class                 = '\\think\\response\\' . ucfirst($type);
            self::$instance[$type] = class_exists($class) ? new $class($options) : $this;
        }
        if (isset($this->contentType[$type])) {
            self::$instance[$type]->contentType($this->contentType[$type]);
        }
        return self::$instance[$type];
    }

    /**
     * 输出是否exit设置
     * @access public
     * @param bool $exit 是否退出
     * @return $this
     */
    public function isExit($exit)
    {
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
    public function result($data, $code = 0, $msg = '')
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => NOW_TIME,
            'data' => $data,
        ];
        return $this->data($result);
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
        $this->header[$name] = $value;
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
}
