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

class Input
{
    // 全局过滤规则
    public static $filters = [];
    //就使用全局$GLOBALS
    public static $data = [];
    public $method      = ['get', 'post', 'put', 'param', 'request', 'session', 'cookie', 'server', 'url', 'env', 'file'];

    public function __construct()
    {
        parse_str(file_get_contents('php://input'), $GLOBALS['_PUT']);
        $GLOBALS['_URL'] = [];
        foreach (explode('/', $_SERVER['PATH_INFO']) as $key => $value) {
            if (!empty($value)) {
                $GLOBALS['_URL'][$key] = $value;
            }
        }
        $GLOBALS['_SESSION'] = $_SESSION;
        self::$data          = $GLOBALS;
    }

    /**
     * 获取get变量
     * @param string $method 方法名称
     * @param string $name 数据名称
     * @param string $default 默认值
     * @param string $filter 过滤方法
     * @param boolean $merge 是否与默认的过虑方法合并
     * @return mixed
     */
    public static function get($method = '', $name = '', $default = null, $filter = null, $merge = false)
    {
        if (!empty($method)) {
            return self::$data["_" . strtoupper($method)][$name];
        }
        //自动判断
        foreach ($this->method as $key => $value) {
            if (isset(self::$data["_" . strtoupper($value)][$name])) {
                return self::$data["_" . strtoupper($value)][$name];
            }
        }
    }
}
