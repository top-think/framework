<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

class View
{
    /**
     * 模板引擎实例
     * @var object
     */
    public $engine;

    /**
     * 模板变量
     * @var array
     */
    protected $data = [];

    /**
     * 内容过滤
     * @var mixed
     */
    protected $filter;

    /**
     * 全局模板变量
     * @var array
     */
    protected static $var = [];

    /**
     * 初始化
     * @access public
     * @param  mixed $engine  模板引擎参数
     * @return $this
     */
    public function init($engine = [])
    {
        // 初始化模板引擎
        $this->engine($engine);

        return $this;
    }

    public static function __make(Config $config)
    {
        return (new static())->init($config->pull('template'));
    }

    /**
     * 模板变量静态赋值
     * @access public
     * @param  array $vars  变量名
     * @return $this
     */
    public function share(array $vars)
    {
        self::$var = array_merge(self::$var, $vars);

        return $this;
    }

    /**
     * 模板变量赋值
     * @access public
     * @param  array $vars  模板变量
     * @return $this
     */
    public function assign(array $vars)
    {
        $this->data = array_merge($this->data, $vars);

        return $this;
    }

    /**
     * 设置当前模板解析的引擎
     * @access public
     * @param  array|string $options 引擎参数
     * @return $this
     */
    public function engine($options = [])
    {
        if (is_string($options)) {
            $type    = $options;
            $options = [];
        } else {
            $type = !empty($options['type']) ? $options['type'] : 'Think';
        }

        if (isset($options['type'])) {
            unset($options['type']);
        }

        $this->engine = App::factory($type, '\\think\\view\\driver\\', $options);

        return $this;
    }

    /**
     * 配置模板引擎
     * @access public
     * @param  array  $name 模板参数
     * @return $this
     */
    public function config(array $config)
    {
        $this->engine->config($config);

        return $this;
    }

    /**
     * 检查模板是否存在
     * @access public
     * @param  string  $name 参数名
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->engine->exists($name);
    }

    /**
     * 视图过滤
     * @access public
     * @param Callable  $filter 过滤方法或闭包
     * @return $this
     */
    public function filter(callable $filter = null)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * 解析和获取模板内容 用于输出
     * @access public
     * @param  string    $template 模板文件名或者内容
     * @param  array     $vars     模板输出变量
     * @param  array     $config     模板参数
     * @param  bool      $renderContent     是否渲染内容
     * @return string
     * @throws \Exception
     */
    public function fetch(string $template = '', array $vars = [], array $config = [], bool $renderContent = false): string
    {
        // 模板变量
        $vars = array_merge(self::$var, $this->data, $vars);

        // 页面缓存
        ob_start();
        ob_implicit_flush(0);

        // 渲染输出
        try {
            $method = $renderContent ? 'display' : 'fetch';
            $this->engine->$method($template, $vars, $config);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        // 获取并清空缓存
        $content = ob_get_clean();

        if ($this->filter) {
            $content = call_user_func_array($this->filter, [$content]);
        }

        return $content;
    }

    /**
     * 渲染内容输出
     * @access public
     * @param  string $content 内容
     * @param  array  $vars    模板输出变量
     * @param  array  $config  模板参数
     * @return string
     */
    public function display(string $content, array $vars = [], array $config = []): string
    {
        return $this->fetch($content, $vars, $config, true);
    }

    /**
     * 模板变量赋值
     * @access public
     * @param  string    $name  变量名
     * @param  mixed     $value 变量值
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * 取得模板显示变量的值
     * @access protected
     * @param  string $name 模板变量
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name];
    }

    /**
     * 检测模板变量是否设置
     * @access public
     * @param  string $name 模板变量名
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
