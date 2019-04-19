<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

use Yaconf;

/**
 * 配置管理类
 */
class Config
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 配置文件目录
     * @var string
     */
    protected $path;

    /**
     * 配置文件后缀
     * @var string
     */
    protected $ext;

    /**
     * 是否支持Yaconf
     * @var bool|string
     */
    protected $yaconf;

    /**
     * 构造方法
     * @access public
     */
    public function __construct(string $path = null, string $ext = '.php')
    {
        $this->path   = $path ?: '';
        $this->ext    = $ext;
        $this->yaconf = class_exists('Yaconf');
    }

    public static function __make(App $app)
    {
        $path = $app->getConfigPath();
        $ext  = $app->getConfigExt();

        return new static($path, $ext);
    }

    /**
     * 设置开启Yaconf 或者指定配置文件名
     * @access public
     * @param  bool|string $yaconf 是否使用Yaconf
     * @return void
     */
    public function setYaconf($yaconf): void
    {
        $this->yaconf = $yaconf;
    }

    /**
     * 获取实际的yaconf配置参数名
     * @access protected
     * @param  string $name 配置参数名
     * @return string
     */
    protected function getYaconfName(string $name)
    {
        if ($this->yaconf && is_string($this->yaconf)) {
            return $this->yaconf . '.' . $name;
        }

        return $name;
    }

    /**
     * 获取yaconf配置
     * @access public
     * @param  string $name 配置参数名
     * @param  mixed  $default   默认值
     * @return mixed
     */
    public function yaconf(string $name, $default = null)
    {
        if ($this->yaconf) {
            $yaconfName = $this->getYaconfName($name);

            if (Yaconf::has($yaconfName)) {
                return Yaconf::get($yaconfName);
            }
        }

        return $default;
    }

    /**
     * 加载配置文件（多种格式）
     * @access public
     * @param  string $file 配置文件名
     * @param  string $name 一级配置名
     * @return array
     */
    public function load(string $file, string $name = ''): array
    {
        if (is_file($file)) {
            $filename = $file;
        } elseif (is_file($this->path . $file . $this->ext)) {
            $filename = $this->path . $file . $this->ext;
        }

        if (isset($filename)) {
            return $this->parse($filename, $name);
        }

        if ($this->yaconf && Yaconf::has($file)) {
            return $this->set(Yaconf::get($file), $name);
        }

        return $this->config;
    }

    /**
     * 解析配置文件
     * @access public
     * @param  string $file 配置文件名
     * @param  string $name 一级配置名
     * @return array
     */
    protected function parse(string $file, string $name): array
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);

        switch ($type) {
            case 'php':
                $config = include $file;
                break;
            case 'yaml':
                if (function_exists('yaml_parse_file')) {
                    $config = yaml_parse_file($file);
                }
                break;
            case 'ini':
                $config = parse_ini_file($file, true) ?: [];
                break;
            case 'json':
                $config = json_decode(file_get_contents($file), true);
                break;
        }

        return isset($config) && is_array($config) ? $this->set($config, strtolower($name)) : [];
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param  string $name 配置参数名（支持多级配置 .号分割）
     * @return bool
     */
    public function has(string $name): bool
    {
        return !is_null($this->get($name));
    }

    /**
     * 获取一级配置
     * @access protected
     * @param  string $name 一级配置名
     * @return array
     */
    protected function pull(string $name): array
    {
        $name = strtolower($name);

        if ($this->yaconf) {
            $yaconfName = $this->getYaconfName($name);

            if (Yaconf::has($yaconfName)) {
                $config = Yaconf::get($yaconfName);
                return isset($this->config[$name]) ? array_merge($this->config[$name], $config) : $config;
            }
        }

        return $this->config[$name] ?? [];
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param  string $name    配置参数名（支持多级配置 .号分割）
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $name = null, $default = null)
    {
        // 无参数时获取所有
        if (empty($name)) {
            return $this->config;
        }

        if (false === strpos($name, '.')) {
            return $this->pull($name);
        }

        if ($this->yaconf) {
            $yaconfName = $this->getYaconfName($name);

            if (Yaconf::has($yaconfName)) {
                return Yaconf::get($yaconfName);
            }
        }

        $name    = explode('.', $name);
        $name[0] = strtolower($name[0]);
        $config  = $this->config;

        // 按.拆分成多维数组进行判断
        foreach ($name as $val) {
            if (isset($config[$val])) {
                $config = $config[$val];
            } else {
                return $default;
            }
        }

        return $config;
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @access public
     * @param  array  $config 配置参数
     * @param  string $name 配置名
     * @return array
     */
    public function set(array $config, string $name = null): array
    {
        if (!empty($name)) {
            if (isset($this->config[$name])) {
                $result = array_merge($this->config[$name], $config);
            } else {
                $result = $config;
            }

            $this->config[$name] = $result;
        } else {
            $result = $this->config = array_merge($this->config, array_change_key_case($config));
        }

        return $result;
    }

    /**
     * 移除配置
     * @access public
     * @param  string $name 配置参数名（支持三级配置 .号分割）
     * @return void
     */
    public function remove(string $name): void
    {
        $name = explode('.', $name, 3);

        if (count($name) == 2) {
            unset($this->config[strtolower($name[0])][$name[1]]);
        } else {
            unset($this->config[strtolower($name[0])][$name[1]][$name[2]]);
        }
    }

    /**
     * 重置配置参数
     * @access public
     * @param  string $name 配置名
     * @return void
     */
    public function reset(string $name = ''): void
    {
        if ('' === $name) {
            $this->config = [];
        } else {
            $this->config[$name] = [];
        }
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
