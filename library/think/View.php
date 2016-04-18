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

class View
{
    // 视图实例
    protected static $instance = null;
    // 模板引擎实例
    public $engine = null;
    // 模板变量
    protected $data = [];
    // 视图参数
    protected $config = [
        // 视图输出字符串替换
        'parse_str'     => [],
        // 视图驱动命名空间
        'namespace'     => '\\think\\view\\driver\\',
        'engine_type'   => 'think',
        // 模板引擎配置参数
        'engine_config' => [],
    ];

    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config($config);
        }
    }

    /**
     * 初始化视图
     * @access public
     * @param array $config  配置参数
     * @return object
     */
    public static function instance($config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 模板变量赋值
     * @access public
     * @param mixed $name  变量名
     * @param mixed $value 变量值
     * @return $this
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->data = array_merge($this->data, $name);
            return $this;
        } else {
            $this->data[$name] = $value;
        }
        return $this;
    }

    /**
     * 设置视图参数
     * @access public
     * @param mixed $config 视图参数或者数组
     * @param string $value 值
     * @return mixed
     */
    public function config($config = '', $value = null)
    {
        if (is_array($config)) {
            foreach ($this->config as $key => $val) {
                if (isset($config[$key])) {
                    $this->config[$key] = $config[$key];
                }
            }
        } elseif (is_null($value)) {
            // 获取配置参数
            return $this->config[$config];
        } else {
            $this->config[$config] = $value;
        }
        return $this;
    }

    /**
     * 设置当前模板解析的引擎
     * @access public
     * @param string $engine 引擎名称
     * @param array $config 引擎参数
     * @return $this
     */
    public function engine($engine, array $config = [])
    {
        $class        = $this->config['namespace'] . ucfirst($engine);
        $this->engine = new $class($config);
        return $this;
    }

    /**
     * 解析和获取模板内容 用于输出
     * @param string $template 模板文件名或者内容
     * @param array  $vars     模板输出变量
     * @param array  $config     模板参数
     * @param bool   $renderContent 是否渲染内容
     * @return string
     * @throws Exception
     */
    public function fetch($template = '', $vars = [], $config = [], $renderContent = false)
    {
        // 模板变量
        $vars = array_merge($this->data, $vars);
        if (is_null($this->engine)) {
            // 初始化模板引擎
            $this->engine($this->config['engine_type'], $this->config['engine_config']);
        }
        // 页面缓存
        ob_start();
        ob_implicit_flush(0);

        // 渲染输出
        $method = $renderContent ? 'display' : 'fetch';
        $this->engine->$method($template, $vars, $config);

        // 获取并清空缓存
        $content = ob_get_clean();
        // 内容过滤标签
        APP_HOOK && Hook::listen('view_filter', $content);
        // 允许用户自定义模板的字符串替换
        if (!empty($this->config['parse_str'])) {
            $replace = $this->config['parse_str'];
            $content = str_replace(array_keys($replace), array_values($replace), $content);
        }
        if (!Config::get('response_auto_output')) {
            // 自动响应输出
            return Response::send($content, Response::type());
        }
        return $content;
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array  $vars    模板输出变量
     * @param array  $config     模板参数
     * @return mixed
     */
    public function display($content, $vars = [], $config = [])
    {
        return $this->fetch($content, $vars, $config, true);
    }

    /**
     * 模板变量赋值
     * @access public
     * @param string $name  变量名
     * @param mixed $value 变量值
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * 取得模板显示变量的值
     * @access protected
     * @param string $name 模板变量
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name];
    }

    /**
     * 检测模板变量是否设置
     * @access public
     * @param string $name 模板变量名
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
