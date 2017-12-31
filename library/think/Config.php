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

namespace think;

class Config
{
    /**
     * 配置参数
     * @var array
     */
    private $config = [];

    /**
     * 缓存前缀
     * @var string
     */
    private $prefix = 'app';

    /**
     * 设置配置参数默认前缀
     * @access public
     * @param string    $prefix 前缀
     * @return void
     */
    public function setDefaultPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * 解析配置文件或内容
     * @access public
     * @param  string    $config 配置文件路径或内容
     * @param  string    $type 配置解析类型
     * @param  string    $name 配置名（如设置即表示二级配置）
     * @return mixed
     */
    public function parse($config, $type = '', $name = '')
    {
        if (empty($type)) {
            $type = pathinfo($config, PATHINFO_EXTENSION);
        }

        $class = false !== strpos($type, '\\') ? $type : '\\think\\config\\driver\\' . ucwords($type);

        return $this->set((new $class())->parse($config), $name);
    }

    /**
     * 加载配置文件（多种格式）
     * @access public
     * @param  string    $file 配置文件名
     * @param  string    $name 一级配置名
     * @return mixed
     */
    public function load($file, $name = '')
    {
        if (is_file($file)) {
            $name = strtolower($name);
            $type = pathinfo($file, PATHINFO_EXTENSION);

            if ('php' == $type) {
                return $this->set(include $file, $name);
            } elseif ('yaml' == $type && function_exists('yaml_parse_file')) {
                return $this->set(yaml_parse_file($file), $name);
            } else {
                return $this->parse($file, $type, $name);
            }
        } else {
            return $this->config;
        }
    }

    /**
     * 自动加载配置文件（PHP格式）
     * @access public
     * @param  string    $name 配置名
     * @return void
     */
    protected function autoLoad($name)
    {
        // 如果尚未载入 则动态加载配置文件
        $module = Container::get('request')->module();
        $module = $module ? $module . '/' : '';
        $app    = Container::get('app');
        $path   = $app->getAppPath() . $module;

        if (is_dir($path . 'config')) {
            $file = $path . 'config/' . $name . $app->getConfigExt();
        } elseif (is_dir($app->getConfigPath() . $module)) {
            $file = $app->getConfigPath() . $module . $name . $app->getConfigExt();
        }

        if (isset($file) && is_file($file)) {
            $this->load($file, $name);
        }
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param  string    $name 配置参数名（支持多级配置 .号分割）
     * @return bool
     */
    public function has($name)
    {
        if (!strpos($name, '.')) {
            $name = $this->prefix . '.' . $name;
        }

        return $this->get($name) ? true : false;
    }

    /**
     * 获取一级配置
     * @access public
     * @param  string    $name 一级配置名
     * @return array
     */
    public function pull($name)
    {
        $name = strtolower($name);

        if (!isset($this->config[$name])) {
            // 如果尚未载入 则动态加载配置文件
            $this->autoLoad($name);
        }

        return isset($this->config[$name]) ? $this->config[$name] : [];
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string    $name 配置参数名（支持多级配置 .号分割）
     * @return mixed
     */
    public function get($name = null)
    {
        // 无参数时获取所有
        if (empty($name)) {
            return $this->config;
        }

        if (!strpos($name, '.')) {
            $name = $this->prefix . '.' . $name;
        } elseif ('.' == substr($name, -1)) {
            return $this->pull(substr($name, 0, -1));
        }

        $name    = explode('.', $name);
        $name[0] = strtolower($name[0]);
        $config  = $this->config;

        if (!isset($config[$name[0]])) {
            // 如果尚未载入 则动态加载配置文件
            $this->autoLoad($name[0]);
        }

        // 按.拆分成多维数组进行判断
        foreach ($name as $val) {
            if (isset($config[$val])) {
                $config = $config[$val];
            } else {
                return;
            }
        }

        return $config;
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @access public
     * @param  string|array  $name 配置参数名（支持二级配置 .号分割）
     * @param  mixed         $value 配置值
     * @return mixed
     */
    public function set($name, $value = null)
    {
        if (is_string($name)) {
            if (!strpos($name, '.')) {
                $name = $this->prefix . '.' . $name;
            }

            $name = explode('.', $name);

            $this->config[strtolower($name[0])][$name[1]] = $value;
            return $value;
        } elseif (is_array($name)) {
            // 批量设置
            if (!empty($value)) {
                if (isset($this->config[$value])) {
                    $result = array_merge($this->config[$value], $name);
                } else {
                    $result = $name;
                }

                $this->config[$value] = $result;
            } else {
                $result = $this->config = array_merge($this->config, $name);
            }
        } else {
            // 为空直接返回 已有配置
            $result = $this->config;
        }

        return $result;
    }

    /**
     * 重置配置参数
     * @access public
     * @param  string    $prefix  配置前缀名
     * @return void
     */
    public function reset($prefix = '')
    {
        if ('' === $prefix) {
            $this->config = [];
        } else {
            $this->config[$prefix] = [];
        }
    }

    /**
     * 设置配置
     * @access public
     * @param  string    $name  参数名
     * @param  mixed     $value 值
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * 获取配置参数
     * @access public
     * @param  string $name 参数名
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * 检测是否存在参数
     * @access public
     * @param  string $name 参数名
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

}
