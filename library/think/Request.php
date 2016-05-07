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

use think\Config;
use think\Input;

class Request
{
    /**
     * @var string 基础URL
     */
    protected static $baseUrl;

    /**
     * @var string 根目录
     */
    protected static $root;

    /**
     * @var string pathinfo
     */
    protected static $pathinfo;

    /**
     * @var string pathinfo（不含后缀）
     */
    protected static $path;

    /**
     * @var array 路由
     */
    protected static $route = [];

    /**
     * @var array 调度信息
     */
    protected static $dispatch = [];

    /**
     * @var array 资源类型
     */
    protected static $mime = [
        'html' => 'text/html,application/xhtml+xml,*/*',
        'xml'  => 'application/xml,text/xml,application/x-xml',
        'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js'   => 'text/javascript,application/javascript,application/x-javascript',
        'css'  => 'text/css',
        'rss'  => 'application/rss+xml',
        'yaml' => 'application/x-yaml,text/yaml',
        'atom' => 'application/atom+xml',
        'pdf'  => 'application/pdf',
        'text' => 'text/plain',
        'png'  => 'image/png',
        'jpg'  => 'image/jpg,image/jpeg,image/pjpeg',
        'gif'  => 'image/gif',
        'csv'  => 'text/csv',
    ];

    /**
     * 获取当前URL
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public static function url($url = '')
    {
        if (!empty($url)) {
            self::$url = $url;
        } else {
            return self::$url ?: $_SERVER[Config::get('url_request_uri')];
        }
    }

    /**
     * 获取基础URL
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public static function baseUrl($url = '')
    {
        if (!empty($url)) {
            self::$baseUrl = $url;
        } else {
            return self::$baseUrl ?: rtrim($_SERVER['SCRIPT_NAME'], '/');
        }
    }

    /**
     * 获取URL访问根目录
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public static function root($url = '')
    {
        if (!empty($url)) {
            self::$root = $url;

        } elseif (self::$root) {
            return self::$root;
        } else {
            $_root = rtrim(dirname(self::baseUrl()), '/');
            return ('/' == $_root || '\\' == $_root) ? '' : $_root;
        }
    }

    /**
     * 获取当前请求URL的pathinfo信息（含URL后缀）
     * @access public
     * @return string
     */
    public static function pathinfo()
    {
        if (is_null(self::$pathinfo)) {
            if (isset($_GET[Config::get('var_pathinfo')])) {
                // 判断URL里面是否有兼容模式参数
                $_SERVER['PATH_INFO'] = $_GET[Config::get('var_pathinfo')];
                unset($_GET[Config::get('var_pathinfo')]);
            } elseif (IS_CLI) {
                // CLI模式下 index.php module/controller/action/params/...
                $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            }

            // 分析PATHINFO信息
            if (!isset($_SERVER['PATH_INFO'])) {
                foreach (Config::get('pathinfo_fetch') as $type) {
                    if (!empty($_SERVER[$type])) {
                        $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type], $_SERVER['SCRIPT_NAME'])) ?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
                        break;
                    }
                }
            }
            self::$pathinfo = empty($_SERVER['PATH_INFO']) ? '/' : trim($_SERVER['PATH_INFO'], '/');
        }
        return self::$pathinfo;
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access public
     * @return string
     */
    public static function path()
    {
        if (is_null(self::$path)) {
            // 去除正常的URL后缀
            self::$path = preg_replace(Config::get('url_html_suffix') ? '/\.(' . trim(Config::get('url_html_suffix'), '.') . ')$/i' : '/\.' . self::ext() . '$/i', '', self::pathinfo());
        }
        return self::$path;
    }

    /**
     * 当前URL的访问后缀
     * @access public
     * @return string
     */
    public static function ext()
    {
        return pathinfo(self::pathinfo(), PATHINFO_EXTENSION);
    }

    /**
     * 获取当前请求的时间
     * @access public
     * @param bool $float 是否使用浮点类型
     * @return integer|float
     */
    public static function time($float = false)
    {
        return $float ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
    }

    /**
     * 当前请求的资源类型
     * @access public
     * @return false|string
     */
    public static function type()
    {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }

        foreach (self::$mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($_SERVER['HTTP_ACCEPT'], $v)) {
                    return $key;
                }
            }
        }
        return false;
    }

    /**
     * 设置资源类型
     * @access public
     * @param string|array $type 资源类型名
     * @param string $val 资源类型
     * @return void
     */
    public static function mimeType($type, $val = '')
    {
        if (is_array($type)) {

            self::$mimeType = array_merge(self::$mimeType, $type);
        } else {
            self::$mimeType[$type] = $val;
        }
    }

    /**
     * 当前的请求类型
     * @access public
     * @return string
     */
    public static function method()
    {
        return IS_CLI ? 'GET' : $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 当前请求的参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function param($name = '')
    {
        return Input::param($name);
    }

    /**
     * 当前请求的get参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function get($name = '')
    {
        return Input::get($name);
    }

    /**
     * 当前请求的post参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function post($name = '')
    {
        return Input::post($name);
    }

    /**
     * 当前请求的put参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function put($name = '')
    {
        return Input::put($name);
    }

    /**
     * 当前请求的delete参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function delete($name = '')
    {
        return Input::delete($name);
    }

    /**
     * 获取session数据
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function session($name = '')
    {
        return Input::session($name);
    }

    /**
     * 获取cookie参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function cookie($name = '')
    {
        return Input::cookie($name);
    }

    /**
     * 获取server参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public static function server($name = '')
    {
        return Input::server($name);
    }

    /**
     * 获取上传的文件信息
     * @access public
     * @param string $name 名称
     * @return null|array|\think\File
     */
    public static function file($name = '')
    {
        return Input::file($name);
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public static function isSsl()
    {
        if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
            return true;
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }
        return false;
    }

    /**
     * 当前是否ajax请求
     * @access public
     * @return bool
     */
    public static function isAjax()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ? true : false;
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public static function ip($type = 0, $adv = false)
    {
        $type      = $type ? 1 : 0;
        static $ip = null;
        if (null !== $ip) {
            return $ip[$type];
        }

        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }

                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public static function scheme()
    {
        return $_SERVER['REQUEST_SCHEME'];
    }

    /**
     * 当前请求URL地址中的query参数
     * @access public
     * @return string
     */
    public static function query()
    {
        return $_SERVER['QUERY_STRING'];
    }

    /**
     * 当前请求的host
     * @access public
     * @return string
     */
    public static function host()
    {
        return $_SERVER['SERVER_NAME'];
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return integer
     */
    public static function port()
    {
        return $_SERVER['SERVER_PORT'];
    }

    /**
     * 获取当前请求的路由
     * @access public
     * @param array $route 路由名称
     * @return array
     */
    public static function route($route = [])
    {
        if (!empty($route)) {
            self::$route = $route;
        } else {
            return self::$route;
        }
    }

    /**
     * 获取当前请求的调度信息
     * @access public
     * @param array $dispatch 调度信息
     * @return array
     */
    public static function dispatch($dispatch = [])
    {
        if (!empty($dispatch)) {
            self::$dispatch = $dispatch;
        }
        return self::$dispatch;
    }

}
