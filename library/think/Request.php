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
use think\Session;

class Request
{
    /**
     * @var object 对象实例
     */
    protected static $instance;

    protected $method;
    /**
     * @var string 域名
     */
    protected $domain;

    /**
     * @var string URL地址
     */
    protected $url;

    /**
     * @var string 基础URL
     */
    protected $baseUrl;

    /**
     * @var string 当前执行的文件
     */
    protected $baseFile;

    /**
     * @var string 访问的ROOT地址
     */
    protected $root;

    /**
     * @var string pathinfo
     */
    protected $pathinfo;

    /**
     * @var string pathinfo（不含后缀）
     */
    protected $path;

    /**
     * @var array 路由
     */
    protected $route = [];

    /**
     * @var array 调度信息
     */
    protected $dispatch = [];

    protected $module;
    protected $controller;
    protected $action;

    /**
     * @var array 请求参数
     */
    protected $param   = [];
    protected $session = [];
    protected $file    = [];
    protected $cookie  = [];
    protected $server  = [];

    /**
     * @var array 资源类型
     */
    protected $mimeType = [
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
     * 架构函数
     * @access public
     * @param array $options 参数
     */
    public function __construct($options = [])
    {
        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }
    }

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return \think\Request
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    /**
     * 创建一个URL请求
     * @access public
     * @param string $uri URL地址
     * @param string $method 请求类型
     * @param array $params 请求参数
     * @param array $cookie
     * @param array $files
     * @param array $server
     * @return \think\Request
     */
    public static function create($uri, $method = 'GET', $params = [], $cookie = [], $files = [], $server = [])
    {
        $server['PATH_INFO']      = '';
        $server['REQUEST_METHOD'] = strtoupper($method);
        $info                     = parse_url($uri);
        if (isset($info['host'])) {
            $server['SERVER_NAME'] = $info['host'];
            $server['HTTP_HOST']   = $info['host'];
        }
        if (isset($info['scheme'])) {
            if ('https' === $info['scheme']) {
                $server['HTTPS']       = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }
        if (isset($info['port'])) {
            $server['SERVER_PORT'] = $info['port'];
            $server['HTTP_HOST']   = $server['HTTP_HOST'] . ':' . $info['port'];
        }
        if (isset($info['user'])) {
            $server['PHP_AUTH_USER'] = $info['user'];
        }
        if (isset($info['pass'])) {
            $server['PHP_AUTH_PW'] = $info['pass'];
        }
        if (!isset($info['path'])) {
            $info['path'] = '/';
        }
        $options          = [];
        $options['param'] = $params;
        $queryString      = '';
        if (isset($info['query'])) {
            parse_str(html_entity_decode($info['query']), $query);
            if (!empty($options['param'])) {
                $options['param'] = array_replace($query, $options['param']);
                $queryString      = http_build_query($query, '', '&');
            } else {
                $options['param'] = $query;
                $queryString      = $info['query'];
            }
        } elseif (isset($options['param'])) {
            $queryString = http_build_query($options['param'], '', '&');
        }
        $server['REQUEST_URI']  = $info['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;
        $options['cookie']      = $cookie;
        $options['file']        = $files;
        $options['server']      = $server;
        return new self($options);
    }

    /**
     * 获取当前包含协议的域名
     * @access public
     * @param string $domain 域名
     * @return string
     */
    public function domain($domain = null)
    {
        if (!is_null($domain)) {
            $this->domain = $domain;
            return $this;
        } elseif (!$this->domain) {
            $this->domain = $this->scheme() . '://' . $this->host();
        }
        return $this->domain;
    }

    /**
     * 获取当前完整URL 包括QUERY_STRING
     * @access public
     * @param string|true $url URL地址 true 带域名获取
     * @return string
     */
    public function url($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->url = $url;
            return $this;
        } elseif (!$this->url) {
            if (IS_CLI) {
                $this->url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                $this->url = $_SERVER['HTTP_X_REWRITE_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $this->url = $_SERVER['REQUEST_URI'];
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
                $this->url = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
            } else {
                $this->url = '';
            }
        }
        return true === $url ? $this->domain() . $this->url : $this->url;
    }

    /**
     * 获取当前URL 不含QUERY_STRING
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public function baseUrl($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->baseUrl = $url;
            return $this;
        } elseif (!$this->baseUrl) {
            $str           = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }
        return true === $url ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * 获取当前执行的文件 SCRIPT_NAME
     * @access public
     * @param string $file 当前执行的文件
     * @return string
     */
    public function baseFile($file = null)
    {
        if (!is_null($file) && true !== $file) {
            $this->baseFile = $file;
            return $this;
        } elseif (!$this->baseFile) {
            $url = '';
            if (!IS_CLI) {
                $script_name = basename($_SERVER['SCRIPT_FILENAME']);
                if (basename($_SERVER['SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['SCRIPT_NAME'];
                } elseif (basename($_SERVER['PHP_SELF']) === $script_name) {
                    $url = $_SERVER['PHP_SELF'];
                } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
                    $url = $_SERVER['ORIG_SCRIPT_NAME'];
                } elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
                    $url = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
                } elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0) {
                    $url = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
                }
            }
            $this->baseFile = $url;
        }
        return true === $file ? $this->domain() . $this->baseFile : $this->baseFile;
    }

    /**
     * 获取URL访问根地址
     * @access public
     * @param string $url URL地址
     * @return string
     */
    public function root($url = null)
    {
        if (!is_null($url) && true !== $url) {
            $this->root = $url;
            return $this;
        } elseif (!$this->root) {
            $file = $this->baseFile();
            if ($file && 0 !== strpos($this->url(), $file)) {
                $file = str_replace('\\', '/', dirname($file));
            }
            $this->root = rtrim($file, '/');
        }
        return true === $url ? $this->domain() . $this->root : $this->root;
    }

    /**
     * 获取当前请求URL的pathinfo信息（含URL后缀）
     * @access public
     * @return string
     */
    public function pathinfo()
    {
        if (is_null($this->pathinfo)) {
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
            $this->pathinfo = empty($_SERVER['PATH_INFO']) ? '/' : ltrim($_SERVER['PATH_INFO'], '/');
        }
        return $this->pathinfo;
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access public
     * @return string
     */
    public function path()
    {
        if (is_null($this->path)) {
            // 去除正常的URL后缀
            $this->path = preg_replace(Config::get('url_html_suffix') ? '/\.(' . trim(Config::get('url_html_suffix'), '.') . ')$/i' : '/\.' . $this->ext() . '$/i', '', $this->pathinfo());
        }
        return $this->path;
    }

    /**
     * 当前URL的访问后缀
     * @access public
     * @return string
     */
    public function ext()
    {
        return pathinfo($this->pathinfo(), PATHINFO_EXTENSION);
    }

    /**
     * 获取当前请求的时间
     * @access public
     * @param bool $float 是否使用浮点类型
     * @return integer|float
     */
    public function time($float = false)
    {
        return $float ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
    }

    /**
     * 当前请求的资源类型
     * @access public
     * @return false|string
     */
    public function type()
    {
        $accept = isset($this->server['HTTP_ACCEPT']) ? $this->server['HTTP_ACCEPT'] : $_SERVER['HTTP_ACCEPT'];
        if (empty($accept)) {
            return false;
        }

        foreach ($this->mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($accept, $v)) {
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
    public function mimeType($type, $val = '')
    {
        if (is_array($type)) {
            $this->mimeType = array_merge($this->mimeType, $type);
        } else {
            $this->mimeType[$type] = $val;
        }
    }

    /**
     * 当前的请求类型
     * @access public
     * @param bool $method  true 获取原始请求类型
     * @return string
     */
    public function method($method = false)
    {
        if (true === $method) {
            // 获取原始请求类型
            return IS_CLI ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
        } elseif (!$this->method) {
            if (isset($_POST[Config::get('var_method')])) {
                $this->method = strtoupper($_POST[Config::get('var_method')]);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            } else {
                $this->method = IS_CLI ? 'GET' : (isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : $_SERVER['REQUEST_METHOD']);
            }
        }
        return $this->method;
    }

    /**
     * 是否为GET请求
     * @access public
     * @return bool
     */
    public function isGet()
    {
        return $this->method() == 'GET';
    }

    /**
     * 是否为POST请求
     * @access public
     * @return bool
     */
    public function isPost()
    {
        return $this->method() == 'POST';
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut()
    {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete()
    {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead()
    {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch()
    {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions()
    {
        return $this->method() == 'OPTIONS';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * 是否为cgi
     * @access public
     * @return bool
     */
    public function isCgi()
    {
        return strpos(PHP_SAPI, 'cgi') === 0;
    }

    /**
     * 当前请求的参数
     * @access public
     * @param string $name 变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function param($name = '', $default = null)
    {
        if (empty($this->param)) {
            $method = $this->method(true);
            // 自动获取请求变量
            switch ($method) {
                case 'POST':
                    $vars = Input::post();
                    break;
                case 'PUT':
                    $vars = Input::put();
                    break;
                case 'DELETE':
                    $vars = Input::delete();
                    break;
                default:
                    $vars = [];
            }
            // 当前请求参数和URL地址中的参数合并
            $this->param = array_merge(Input::get(), $vars);
        }
        if ($name) {
            return isset($this->param[$name]) ? $this->param[$name] : $default;
        } else {
            return $this->param;
        }
    }

    /**
     * 是否存在某个请求参数
     * @access public
     * @param string $name 变量名
     * @param bool $checkEmpty 是否检测空值
     * @return mixed
     */
    public function has($name, $checkEmpty = false)
    {
        if (empty($this->param)) {
            $param = $this->param();
        } else {
            $param = $this->param;
        }
        if (isset($param[$name])) {
            return ($checkEmpty && '' === $param[$name]) ? false : true;
        } else {
            return false;
        }
    }

    /**
     * 获取指定的参数
     * @access public
     * @param string|array $name 变量名
     * @return mixed
     */
    public function only($name)
    {
        $param = $this->param();
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        $item = [];
        foreach ($name as $key) {
            if (isset($param[$key])) {
                $item[$key] = $param[$key];
            }
        }
        return $item;
    }

    /**
     * 排除指定参数获取
     * @access public
     * @param string|array $name 变量名
     * @return mixed
     */
    public function except($name)
    {
        $param = $this->param();
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        foreach ($name as $key) {
            if (isset($param[$key])) {
                unset($param[$key]);
            }
        }
        return $param;
    }

    /**
     * 获取session数据
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public function session($name = '')
    {
        return Input::data($this->session ?: Session::get(), $name);
    }

    /**
     * 获取cookie参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public function cookie($name = '')
    {
        return Input::data($this->cookie ?: $_COOKIE, $name);
    }

    /**
     * 获取server参数
     * @access public
     * @param string $name 变量名
     * @return mixed
     */
    public function server($name = '')
    {
        return Input::data($this->server ?: $_SERVER, $name);
    }

    /**
     * 获取上传的文件信息
     * @access public
     * @param string $name 名称
     * @return null|array|\think\File
     */
    public function file($name = '')
    {
        return Input::file($name, $this->file ?: $_FILES);
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl()
    {
        $server = array_merge($_SERVER, $this->server);
        if (isset($server['HTTPS']) && ('1' == $server['HTTPS'] || 'on' == strtolower($server['HTTPS']))) {
            return true;
        } elseif (isset($server['REQUEST_SCHEME']) && 'https' == $server['REQUEST_SCHEME']) {
            return true;
        } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
            return true;
        } elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' == $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }
        return false;
    }

    /**
     * 当前是否Ajax请求
     * @access public
     * @return bool
     */
    public function isAjax()
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ? true : false;
    }

    /**
     * 当前是否Pjax请求
     * @access public
     * @return bool
     */
    public function isPjax()
    {
        return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX']) ? true : false;
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function ip($type = 0, $adv = false)
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
     * 检测是否使用手机访问
     * @access public
     * @return bool
     */
    public function isMobile()
    {
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML")) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取请求的user agent信息
     * @access public
     * @return string
     */
    public function agent()
    {
        return $this->server('HTTP_USER_AGENT');
    }

    /**
     * 获取请求的accept encoding
     * @access public
     * @return string
     */
    public function encode()
    {
        return $this->server('HTTP_ACCEPT_ENCODING');
    }

    /**
     * 获取请求的accept language
     * @access public
     * @return string
     */
    public function language()
    {
        return $this->server('HTTP_ACCEPT_LANGUAGE');
    }

    /**
     * 获取请求的cache control
     * @access public
     * @return string
     */
    public function cache()
    {
        return $this->server('HTTP_CACHE_CONTROL');
    }

    /**
     * 获取当前请求的content type
     * @access public
     * @return string
     */
    public function getContentType()
    {
        if (isset($_SERVER["CONTENT_TYPE"])) {
            return $_SERVER["CONTENT_TYPE"];
        } elseif (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
            return $_SERVER["HTTP_CONTENT_TYPE"];
        }
        return null;
    }

    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public function scheme()
    {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * 当前请求URL地址中的query参数
     * @access public
     * @return string
     */
    public function query()
    {
        return $this->server('QUERY_STRING');
    }

    /**
     * 当前请求的host
     * @access public
     * @return string
     */
    public function host()
    {
        return $this->server('HTTP_HOST');
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return integer
     */
    public function port()
    {
        return $this->server('SERVER_PORT');
    }

    /**
     * 当前请求 SERVER_PROTOCOL
     * @access public
     * @return integer
     */
    public function protocol()
    {
        return $this->server('SERVER_PROTOCOL');
    }

    /**
     * 当前请求 REMOTE_PORT
     * @access public
     * @return integer
     */
    public function remotePort()
    {
        return $this->server('REMOTE_PORT');
    }

    /**
     * 获取当前请求的路由
     * @access public
     * @param array $route 路由名称
     * @return array
     */
    public function route($route = [])
    {
        if (!empty($route)) {
            $this->route = $route;
        } else {
            return $this->route;
        }
    }

    /**
     * 获取当前请求的调度信息
     * @access public
     * @param array $dispatch 调度信息
     * @return array
     */
    public function dispatch($dispatch = [])
    {
        if (!empty($dispatch)) {
            $this->dispatch = $dispatch;
        }
        return $this->dispatch;
    }

    /**
     * 设置或者获取当前的模块名
     * @access public
     * @param string $module 模块名
     * @return string
     */
    public function module($module = null)
    {
        if (!is_null($module)) {
            $this->module = $module;
            return $this;
        } else {
            return $this->module ?: '';
        }
    }

    /**
     * 设置或者获取当前的控制器名
     * @access public
     * @param string $controller 控制器名
     * @return string
     */
    public function controller($controller = null)
    {
        if (!is_null($controller)) {
            $this->controller = $controller;
            return $this;
        } else {
            return $this->controller ?: '';
        }
    }

    /**
     * 设置或者获取当前的操作名
     * @access public
     * @param string $action 操作名
     * @return string
     */
    public function action($action = null)
    {
        if (!is_null($action)) {
            $this->action = $action;
            return $this;
        } else {
            return $this->action ?: '';
        }
    }
}
