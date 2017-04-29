<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class Config
{
    // 配置参数
    private $config = [];
    // 参数作用域
    protected $range = '_sys_';
    // 当前参数前缀
    protected $prefix = 'app';

    /**
     * 设置配置参数作用域
     * @access public
     * @param string    $range 作用域
     * @return void
     */
    public function range($range)
    {
        $this->range = $range;

        if (!isset($this->config[$range])) {
            $this->config[$range] = [];
        }
    }

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
     * @param string    $config 配置文件路径或内容
     * @param string    $type 配置解析类型
     * @param string    $name 配置名（如设置即表示二级配置）
     * @param string    $range  作用域
     * @return mixed
     */
    public function parse($config, $type = '', $name = '', $range = '')
    {
        $range = $range ?: $this->range;

        if (empty($type)) {
            $type = pathinfo($config, PATHINFO_EXTENSION);
        }

        $class = false !== strpos($type, '\\') ? $type : '\\think\\config\\driver\\' . ucwords($type);

        return $this->set((new $class())->parse($config), $name, $range);
    }

    /**
     * 加载配置文件（PHP格式）
     * @access public
     * @param string    $file 配置文件名
     * @param string    $name 配置名（如设置即表示二级配置）
     * @param string    $range  作用域
     * @return mixed
     */
    public function load($file, $name = '', $range = '')
    {
        $range = $range ?: $this->range;

        if (!isset($this->config[$range])) {
            $this->config[$range] = [];
        }

        if (is_file($file)) {
            $name = strtolower($name);
            $type = pathinfo($file, PATHINFO_EXTENSION);

            if ('php' == $type) {
                return $this->set(include $file, $name, $range);
            } elseif ('yaml' == $type && function_exists('yaml_parse_file')) {
                return $this->set(yaml_parse_file($file), $name, $range);
            } else {
                return $this->parse($file, $type, $name, $range);
            }
        } else {
            return $this->config[$range];
        }
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param string    $name 配置参数名（支持二级配置 .号分割）
     * @param string    $range  作用域
     * @return bool
     */
    public function has($name, $range = '')
    {
        $range = $range ?: $this->range;

        if (!strpos($name, '.')) {
            $name = $this->prefix . '.' . $name;
        }

        return $this->get($name, $range) ? true : false;
    }

    /**
     * 获取一级配置
     * @access public
     * @param string    $name 一级配置名
     * @param string    $range  作用域
     * @return mixed
     */
    public function pull($name = null, $range = '')
    {
        $range = $range ?: $this->range;

        // 无参数时获取所有
        if (empty($name) && isset($this->config[$range])) {
            return $this->config[$range];
        }

        $name = strtolower($name);

        return isset($this->config[$range][$name]) ? $this->config[$range][$name] : [];
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param string    $name 配置参数名（支持二级配置 .号分割）
     * @param string    $range  作用域
     * @return mixed
     */
    public function get($name = null, $range = '')
    {
        $range = $range ?: $this->range;

        // 无参数时获取所有
        if (empty($name) && isset($this->config[$range])) {
            return $this->config[$range];
        }

        if (!strpos($name, '.')) {
            $name = $this->prefix . '.' . $name;
        }

        $name   = explode('.', strtolower($name));
        $config = $this->config[$range];

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
     * @param string|array  $name 配置参数名（支持二级配置 .号分割）
     * @param mixed         $value 配置值
     * @param string        $range  作用域
     * @return mixed
     */
    public function set($name, $value = null, $range = '')
    {
        $range = $range ?: $this->range;

        if (!isset($this->config[$range])) {
            $this->config[$range] = [];
        }

        if (is_string($name)) {
            if (!strpos($name, '.')) {
                $name = $this->prefix . '.' . $name;
            }
            $name = explode('.', strtolower($name));

            $this->config[$range][$name[0]][$name[1]] = $value;
            return $value;
        } elseif (is_array($name)) {
            // 批量设置
            $name = array_change_key_case($name);
            if (!empty($value)) {
                if (isset($this->config[$range][$value])) {
                    $result = array_merge($this->config[$range][$value], $name);
                } else {
                    $result = $name;
                }
                $this->config[$range][$value] = $result;
            } else {
                $result = $this->config[$range] = array_merge($this->config[$range], $name);
            }
        } else {
            // 为空直接返回 已有配置
            $result = $this->config[$range];
        }

        return $result;
    }

    /**
     * 重置配置参数
     * @access public
     * @param string    $range  作用域
     */
    public function reset($range = '')
    {
        $range = $range ?: $this->range;

        if (true === $range) {
            $this->config = [];
        } else {
            $this->config[$range] = [];
        }
    }

    /**
     * 设置配置
     * @access public
     * @param string    $name  参数名
     * @param mixed     $value 值
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * 获取配置参数
     * @access public
     * @param string $name 参数名
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * 检测是否存在参数
     * @access public
     * @param string $name 参数名
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

}
