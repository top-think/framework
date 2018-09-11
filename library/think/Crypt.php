<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

/**
 * 加密解密类
 */
class Crypt
{
    /**
     * 配置参数
     * @var string
     */
    protected $config = [];

    /**
     * 驱动类型
     * @var array
     */
    private static $defaultDrivers = [
        "\\think\\crypt\\driver\\Think", "\\think\\crypt\\driver\\Base64", "\\think\\crypt\\driver\\Crypt", "\\think\\crypt\\driver\\Des", "\\think\\crypt\\driver\\Xxtea",
    ];

    /**
     * 操作句柄
     * @var object
     */
    private $handler = null;

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    public function __construct(App $app, $config = [])
    {
        $this->app = $app;
        $this->config = $config;
    }


    /**
     * 初始化句柄
     * @param  string $options
     * @return mixed|object|string
     */
    public function init($options = '')
    {

        if (is_null($this->handler)) {
            $type = '';

            if (is_scalar($options)) {
                $type = ucwords(strtolower($options));
                if (empty($type)) {
                    $options = $this->config;
                    if ('complex' == $options['type']) {
                        $default = $options['default'];
                        $type = ucwords(strtolower($default['type']));
                    }
                }
            }

            foreach (self::$defaultDrivers as $driver) {
                if (false !== strpos($driver, $type)) {
                    $class = $driver;
                }
            }

            $this->handler = new $class;
        }

        return $this->handler;
    }

    public static function __make(App $app, Config $config)
    {
        return new static($app, $config->pull('crypt'));
    }

    /**
     * 加密字符串
     * @param string  $data 字符串
     * @param string  $key 加密key
     * @param integer $expire 有效期（秒） 0 为永久有效
     * @return string
     */
    public function encrypt($data, $key, $expire = 0)
    {
        if (empty($this->handler)) {
            $this->init();
        }

        return $this->handler->encrypt($data, $key, $expire);
    }

    /**
     * 解密字符串
     * @param string $data 字符串
     * @param string $key 加密key
     * @return string
     */
    public function decrypt($data, $key)
    {
        if (empty($this->handler)) {
            $this->init();
        }

        return $this->handler->decrypt($data, $key);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->init(), $method], $args);
    }
}
