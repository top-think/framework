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

namespace think\crypt;

/**
 * 加密基础类
 */
abstract class Driver
{
    /**
     * 驱动句柄
     * @var object
     */
    protected $handler = null;

    /**
     * 加密配置
     * @var array
     */
    protected $config = [];


    /**
     * 加密参数
     * @var array
     */
    protected $options = [];


    /**
     * 初始化句柄
     * @param string $type
     * @return mixed
     */
    abstract public function init($type = 'think');

    /**
     * 加密字符串
     * @param string  $data 字符串
     * @param string  $key 加密key
     * @param integer $expire 有效期（秒） 0 为永久有效
     * @return string
     */
    abstract public function encrypt($data, $key, $expire = 0);

    /**
     * 解密字符串
     * @param string $data 字符串
     * @param string $key 加密key
     * @return string
     */
    abstract public function decrypt($data, $key);

    /**
     * 返回句柄对象，可执行其它高级方法
     *
     * @access public
     * @return object
     */
    public function handler()
    {
        return $this->handler;
    }

}
