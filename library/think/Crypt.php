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

use think\facade\Config;

/**
 * 加密解密类
 */
class Crypt
{
    /**
     * 操作句柄
     * @var object
     */
    protected $handler = null;

    /**
     * Crypt constructor.
     * @param string $type
     */
    public function __construct($type = 'think')
    {
        $this->init($type);
    }

    /**
     * 初始化句柄
     * @param string $type
     * @return mixed|object|string
     */
    public function init($type = 'think')
    {
        if (is_null($this->handler)) {
            $options = Config::pull('crypt');
            $class = strpos($options[$type], '\\') ? $options[$type] : 'think\\crypt\\driver\\' . ucwords(strtolower($type));
            $this->handler = $class;
        }

        return $this->handler;
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
