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

use think\facade\Cookie;
use think\facade\Session;
use think\route\Dispatch;

class Request
{
    /**
     * 配置
     * @var array
     */
    protected $config = [
        // PATHINFO变量名 用于兼容模式
        'var_pathinfo'         => 's',
        // 兼容PATH_INFO获取
        'pathinfo_fetch'       => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
        // 表单请求类型伪装变量
        'var_method'           => '_method',
        // 表单ajax伪装变量
        'var_ajax'             => '_ajax',
        // 表单pjax伪装变量
        'var_pjax'             => '_pjax',
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter'       => '',
        // 域名根，如thinkphp.cn
        'url_domain_root'      => '',
        // HTTPS代理标识
        'https_agent_name'     => '',
        // 前端代理服务器IP
        'proxy_server_ip'      => '',
        // 前端代理服务器真实IP头
        'proxy_server_ip_header' => ['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP'],
        // URL伪静态后缀
        'url_html_suffix'      => 'html',
        // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
        'request_cache'        => false,
        // 请求缓存有效期
        'request_cache_expire' => null,
        // 全局请求缓存排除规则
        'request_cache_except' => [],
    ];

    /**
     * 请求类型
     * @var string
     */
    protected $method;

    /**
     * 域名（含协议及端口）
     * @var string
     */
    protected $domain;

    /**
     * 子域名
     * @var string
     */
    protected $subDomain;

    /**
     * 泛域名
     * @var string
     */
    protected $panDomain;

    /**
     * 当前URL地址
     * @var string
     */
    protected $url;

    /**
     * 基础URL
     * @var string
     */
    protected $baseUrl;

    /**
     * 当前执行的文件
     * @var string
     */
    protected $baseFile;

    /**
     * 访问的ROOT地址
     * @var string
     */
    protected $root;

    /**
     * pathinfo
     * @var string
     */
    protected $pathinfo;

    /**
     * pathinfo（不含后缀）
     * @var string
     */
    protected $path;

    /**
     * 当前请求的IP地址
     * @var string
     */
    protected $realIP;

    /**
     * 当前路由信息
     * @var array
     */
    protected $routeInfo = [];

    /**
     * 当前调度信息
     * @var Dispatch
     */
    protected $dispatch;

    /**
     * 当前应用名
     * @var string
     */
    protected $app;

    /**
     * 当前控制器名
     * @var string
     */
    protected $controller;

    /**
     * 当前操作名
     * @var string
     */
    protected $action;

    /**
     * 当前语言集
     * @var string
     */
    protected $langset;

    /**
     * 当前请求参数
     * @var array
     */
    protected $param = [];

    /**
     * 当前GET参数
     * @var array
     */
    protected $get = [];

    /**
     * 当前POST参数
     * @var array
     */
    protected $post = [];

    /**
     * 当前REQUEST参数
     * @var array
     */
    protected $request = [];

    /**
     * 当前ROUTE参数
     * @var array
     */
    protected $route = [];

    /**
     * 中间件传递的参数
     * @var array
     */
    protected $middleware = [];

    /**
     * 当前PUT参数
     * @var array
     */
    protected $put;

    /**
     * 当前SESSION参数
     * @var array
     */
    protected $session = [];

    /**
     * 当前FILE参数
     * @var array
     */
    protected $file = [];

    /**
     * 当前COOKIE参数
     * @var array
     */
    protected $cookie = [];

    /**
     * 当前SERVER参数
     * @var array
     */
    protected $server = [];

    /**
     * 当前ENV参数
     * @var array
     */
    protected $env = [];

    /**
     * 当前HEADER参数
     * @var array
     */
    protected $header = [];

    /**
     * 资源类型定义
     * @var array
     */
    protected $mimeType = [
        'xml'   => 'application/xml,text/xml,application/x-xml',
        'json'  => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js'    => 'text/javascript,application/javascript,application/x-javascript',
        'css'   => 'text/css',
        'rss'   => 'application/rss+xml',
        'yaml'  => 'application/x-yaml,text/yaml',
        'atom'  => 'application/atom+xml',
        'pdf'   => 'application/pdf',
        'text'  => 'text/plain',
        'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
        'csv'   => 'text/csv',
        'html'  => 'text/html,application/xhtml+xml,*/*',
    ];

    /**
     * 当前请求内容
     * @var string
     */
    protected $content;

    /**
     * 全局过滤规则
     * @var array
     */
    protected $filter;

    /**
     * php://input内容
     * @var string
     */
    // php://input
    protected $input;

    /**
     * 请求缓存
     * @var array
     */
    protected $cache;

    /**
     * 缓存是否检查
     * @var bool
     */
    protected $isCheckCache;

    /**
     * 请求安全Key
     * @var string
     */
    protected $secureKey;

    /**
     * 是否合并Param
     * @var bool
     */
    protected $mergeParam = false;

    /**
     * 架构函数
     * @access public
     * @param  array  $options 参数
     */
    public function __construct(array $options = [])
    {
        $this->init($options);

        // 保存 php://input
        $this->input = file_get_contents('php://input');
    }

    public function init(array $options = []): void
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }
    }

    public function config($name = null)
    {
        if (is_null($name)) {
            return $this->config;
        }

        return $this->config[$name] ?? null;
    }

    public static function __make(App $app, Config $config)
    {
        $request = new static($config->get('route'));

        $request->cookie = $app['cookie']->get();
        $request->server = $_SERVER;
        $request->env    = $app['env']->get();

        return $request;
    }

    /**
     * 创建一个URL请求
     * @access public
     * @param  string    $uri URL地址
     * @param  string    $method 请求类型
     * @param  array     $params 请求参数
     * @param  array     $cookie
     * @param  array     $files
     * @param  array     $server
     * @param  string    $content
     * @return \think\Request
     */
    public function create(string $uri, string $method = 'GET', array $params = [], array $cookie = [], array $files = [], array $server = [], string $content = null)
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

        $options     = [];
        $queryString = '';

        $options[strtolower($method)] = $params;

        if (isset($info['query'])) {
            parse_str(html_entity_decode($info['query']), $query);
            if (!empty($params)) {
                $params      = array_replace($query, $params);
                $queryString = http_build_query($params, '', '&');
            } else {
                $params      = $query;
                $queryString = $info['query'];
            }
        } elseif (!empty($params)) {
            $queryString = http_build_query($params, '', '&');
        }

        if ($queryString) {
            parse_str($queryString, $get);
            $options['get'] = isset($options['get']) ? array_merge($get, $options['get']) : $get;
        }

        $server['REQUEST_URI']  = $info['path'] . ('' !== $queryString ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;
        $options['cookie']      = $cookie;
        $options['param']       = $params;
        $options['file']        = $files;
        $options['server']      = $server;
        $options['url']         = $server['REQUEST_URI'];
        $options['baseUrl']     = $info['path'];
        $options['pathinfo']    = '/' == $info['path'] ? '/' : ltrim($info['path'], '/');
        $options['method']      = $server['REQUEST_METHOD'];
        $options['domain']      = isset($info['scheme']) ? $info['scheme'] . '://' . $server['HTTP_HOST'] : '';
        $options['content']     = $content;

        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }

        return $this;
    }

    /**
     * 设置当前包含协议的域名
     * @access public
     * @param  string $domain 域名
     * @return $this
     */
    public function setDomain(string $domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * 获取当前包含协议的域名
     * @access public
     * @param  bool $port 是否需要去除端口号
     * @return string
     */
    public function domain(bool $port = false): string
    {
        return $this->scheme() . '://' . $this->host($port);
    }

    /**
     * 获取当前根域名
     * @access public
     * @return string
     */
    public function rootDomain(): string
    {
        $root = $this->config['url_domain_root'];

        if (!$root) {
            $item  = explode('.', $this->host());
            $count = count($item);
            $root  = $count > 1 ? $item[$count - 2] . '.' . $item[$count - 1] : $item[0];
        }

        return $root;
    }

    /**
     * 获取当前子域名
     * @access public
     * @return string
     */
    public function subDomain(): string
    {
        if (is_null($this->subDomain)) {
            // 获取当前主域名
            $rootDomain = $this->config['url_domain_root'];

            if ($rootDomain) {
                // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
                $domain = explode('.', rtrim(stristr($this->host(), $rootDomain, true), '.'));
            } else {
                $domain = explode('.', $this->host(), -2);
            }

            $this->subDomain = implode('.', $domain);
        }

        return $this->subDomain;
    }

    /**
     * 设置当前泛域名的值
     * @access public
     * @param  string $domain 域名
     * @return $this
     */
    public function setPanDomain(string $domain)
    {
        $this->panDomain = $domain;
        return $this;
    }

    /**
     * 获取当前泛域名的值
     * @access public
     * @return string
     */
    public function panDomain(): string
    {
        return $this->panDomain ?: '';
    }

    /**
     * 设置当前完整URL 包括QUERY_STRING
     * @access public
     * @param  string $url URL地址
     * @return $this
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * 获取当前完整URL 包括QUERY_STRING
     * @access public
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function url(bool $complete = false): string
    {
        if (!$this->url) {
            if ($this->isCli()) {
                $this->url = $_SERVER['argv'][1] ?? '';
            } elseif ($this->server('HTTP_X_REWRITE_URL')) {
                $this->url = $this->server('HTTP_X_REWRITE_URL');
            } elseif ($this->server('REQUEST_URI')) {
                $this->url = $this->server('REQUEST_URI');
            } elseif ($this->server('ORIG_PATH_INFO')) {
                $this->url = $this->server('ORIG_PATH_INFO') . (!empty($this->server('QUERY_STRING')) ? '?' . $this->server('QUERY_STRING') : '');
            } else {
                $this->url = '';
            }
        }

        return $complete ? $this->domain() . $this->url : $this->url;
    }

    /**
     * 设置当前URL 不含QUERY_STRING
     * @access public
     * @param  string $url URL地址
     * @return $this
     */
    public function setBaseUrl(string $url)
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * 获取当前URL 不含QUERY_STRING
     * @access public
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function baseUrl(bool $complete = false): string
    {
        if (!$this->baseUrl) {
            $str           = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }

        return $complete ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * 获取当前执行的文件 SCRIPT_NAME
     * @access public
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function baseFile(bool $complete = false): string
    {
        if (!$this->baseFile) {
            $url = '';
            if (!$this->isCli()) {
                $script_name = basename($this->server('SCRIPT_FILENAME'));
                if (basename($this->server('SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('SCRIPT_NAME');
                } elseif (basename($this->server('PHP_SELF')) === $script_name) {
                    $url = $this->server('PHP_SELF');
                } elseif (basename($this->server('ORIG_SCRIPT_NAME')) === $script_name) {
                    $url = $this->server('ORIG_SCRIPT_NAME');
                } elseif (($pos = strpos($this->server('PHP_SELF'), '/' . $script_name)) !== false) {
                    $url = substr($this->server('SCRIPT_NAME'), 0, $pos) . '/' . $script_name;
                } elseif ($this->server('DOCUMENT_ROOT') && strpos($this->server('SCRIPT_FILENAME'), $this->server('DOCUMENT_ROOT')) === 0) {
                    $url = str_replace('\\', '/', str_replace($this->server('DOCUMENT_ROOT'), '', $this->server('SCRIPT_FILENAME')));
                }
            }
            $this->baseFile = $url;
        }

        return $complete ? $this->domain() . $this->baseFile : $this->baseFile;
    }

    /**
     * 设置URL访问根地址
     * @access public
     * @param  string $url URL地址
     * @return $this
     */
    public function setRoot(string $url)
    {
        $this->root = $url;
        return $this;
    }

    /**
     * 获取URL访问根地址
     * @access public
     * @param  bool $complete 是否包含完整域名
     * @return string
     */
    public function root(bool $complete = false): string
    {
        if (!$this->root) {
            $file = $this->baseFile();
            if ($file && 0 !== strpos($this->url(), $file)) {
                $file = str_replace('\\', '/', dirname($file));
            }
            $this->root = rtrim($file, '/');
        }

        return $complete ? $this->domain() . $this->root : $this->root;
    }

    /**
     * 获取URL访问根目录
     * @access public
     * @return string
     */
    public function rootUrl(): string
    {
        $base = $this->root();
        $root = strpos($base, '.') ? ltrim(dirname($base), DIRECTORY_SEPARATOR) : $base;

        if ('' != $root) {
            $root = '/' . ltrim($root, '/');
        }

        return $root;
    }

    /**
     * 设置当前请求的pathinfo
     * @access public
     * @param  string $pathinfo
     * @return $this
     */
    public function setPathinfo(string $pathinfo)
    {
        $this->pathinfo = $pathinfo;
        return $this;
    }

    /**
     * 获取当前请求URL的pathinfo信息（含URL后缀）
     * @access public
     * @return string
     */
    public function pathinfo(): string
    {
        if (is_null($this->pathinfo)) {
            if (isset($_GET[$this->config['var_pathinfo']])) {
                // 判断URL里面是否有兼容模式参数
                $pathinfo = $_GET[$this->config['var_pathinfo']];
                unset($_GET[$this->config['var_pathinfo']]);
            } elseif ($this->isCli()) {
                // CLI模式下 index.php controller/action/params/...
                $pathinfo = $_SERVER['argv'][1] ?? '';
            } elseif ('cli-server' == PHP_SAPI) {
                $pathinfo = strpos($this->server('REQUEST_URI'), '?') ? strstr($this->server('REQUEST_URI'), '?', true) : $this->server('REQUEST_URI');
            } elseif ($this->server('PATH_INFO')) {
                $pathinfo = $this->server('PATH_INFO');
            }

            // 分析PATHINFO信息
            if (!isset($pathinfo)) {
                foreach ($this->config['pathinfo_fetch'] as $type) {
                    if ($this->server($type)) {
                        $pathinfo = (0 === strpos($this->server($type), $this->server('SCRIPT_NAME'))) ?
                        substr($this->server($type), strlen($this->server('SCRIPT_NAME'))) : $this->server($type);
                        break;
                    }
                }
            }

            $this->pathinfo = empty($pathinfo) || '/' == $pathinfo ? '' : ltrim($pathinfo, '/');
        }

        return $this->pathinfo;
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access public
     * @return string
     */
    public function path(): string
    {
        if (is_null($this->path)) {
            $suffix   = $this->config['url_html_suffix'];
            $pathinfo = $this->pathinfo();
            if (false === $suffix) {
                // 禁止伪静态访问
                $this->path = $pathinfo;
            } elseif ($suffix) {
                // 去除正常的URL后缀
                $this->path = preg_replace('/\.(' . ltrim($suffix, '.') . ')$/i', '', $pathinfo);
            } else {
                // 允许任何后缀访问
                $this->path = preg_replace('/\.' . $this->ext() . '$/i', '', $pathinfo);
            }
        }

        return $this->path;
    }

    /**
     * 当前URL的访问后缀
     * @access public
     * @return string
     */
    public function ext(): string
    {
        return pathinfo($this->pathinfo(), PATHINFO_EXTENSION);
    }

    /**
     * 获取当前请求的时间
     * @access public
     * @param  bool $float 是否使用浮点类型
     * @return integer|float
     */
    public function time(bool $float = false)
    {
        return $float ? $this->server('REQUEST_TIME_FLOAT') : $this->server('REQUEST_TIME');
    }

    /**
     * 当前请求的资源类型
     * @access public
     * @return false|string
     */
    public function type()
    {
        $accept = $this->server('HTTP_ACCEPT');

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
     * @param  string|array  $type 资源类型名
     * @param  string        $val 资源类型
     * @return void
     */
    public function mimeType($type, $val = ''): void
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
     * @param  bool $origin  是否获取原始请求类型
     * @return string
     */
    public function method(bool $origin = false): string
    {
        if ($origin) {
            // 获取原始请求类型
            return $this->server('REQUEST_METHOD') ?: 'GET';
        } elseif (!$this->method) {
            if (isset($_POST[$this->config['var_method']])) {
                $method = strtolower($_POST[$this->config['var_method']]);
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    $this->method    = strtoupper($method);
                    $this->{$method} = $_POST;
                } else {
                    $this->method = 'POST';
                }
                unset($_POST[$this->config['var_method']]);
            } elseif ($this->server('HTTP_X_HTTP_METHOD_OVERRIDE')) {
                $this->method = strtoupper($this->server('HTTP_X_HTTP_METHOD_OVERRIDE'));
            } else {
                $this->method = $this->server('REQUEST_METHOD') ?: 'GET';
            }
        }

        return $this->method;
    }

    /**
     * 是否为GET请求
     * @access public
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method() == 'GET';
    }

    /**
     * 是否为POST请求
     * @access public
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method() == 'POST';
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions(): bool
    {
        return $this->method() == 'OPTIONS';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli(): bool
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * 是否为cgi
     * @access public
     * @return bool
     */
    public function isCgi(): bool
    {
        return strpos(PHP_SAPI, 'cgi') === 0;
    }

    /**
     * 获取当前请求的参数
     * @access public
     * @param  string        $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function param($name = '', $default = null, $filter = '')
    {
        if (empty($this->mergeParam)) {
            $method = $this->method(true);

            // 自动获取请求变量
            switch ($method) {
                case 'POST':
                    $vars = $this->post(false);
                    break;
                case 'PUT':
                case 'DELETE':
                case 'PATCH':
                    $vars = $this->put(false);
                    break;
                default:
                    $vars = [];
            }

            // 当前请求参数和URL地址中的参数合并
            $this->param = array_merge($this->param, $this->get(false), $vars, $this->route(false), $this->file() ?: []);

            $this->mergeParam = true;
        }

        if (is_array($name)) {
            return $this->only($name, $this->param, $filter);
        }

        return $this->input($this->param, $name, $default, $filter);
    }

    /**
     * 设置路由变量
     * @access public
     * @param  array         $route 路由变量
     * @return $this
     */
    public function setRoute(array $route)
    {
        $this->route = array_merge($this->route, $route);
        return $this;
    }

    /**
     * 获取路由参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function route($name = '', $default = null, $filter = '')
    {
        if (is_array($name)) {
            return $this->only($name, $this->route, $filter);
        }

        return $this->input($this->route, $name, $default, $filter);
    }

    /**
     * 获取GET参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function get($name = '', $default = null, $filter = '')
    {
        if (empty($this->get)) {
            $this->get = $_GET;
        }

        if (is_array($name)) {
            return $this->only($name, $this->get, $filter);
        }

        return $this->input($this->get, $name, $default, $filter);
    }

    /**
     * 获取中间件传递的参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @return mixed
     */
    public function middleware($name, $default = null)
    {
        return $this->middleware[$name] ?? $default;
    }

    /**
     * 获取POST参数
     * @access public
     * @param  mixed         $name 变量名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function post($name = '', $default = null, $filter = '')
    {
        if (empty($this->post)) {
            $this->post = !empty($_POST) ? $_POST : $this->getInputData($this->input);
        }

        if (is_array($name)) {
            return $this->only($name, $this->post, $filter);
        }

        return $this->input($this->post, $name, $default, $filter);
    }

    /**
     * 获取PUT参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function put($name = '', $default = null, $filter = '')
    {
        if (is_null($this->put)) {
            $this->put = $this->getInputData($this->input);
        }

        if (is_array($name)) {
            return $this->only($name, $this->put, $filter);
        }

        return $this->input($this->put, $name, $default, $filter);
    }

    protected function getInputData($content)
    {
        if (false !== strpos($this->contentType(), 'application/json') || 0 === strpos($content, '{"')) {
            return (array) json_decode($content, true);
        } elseif (strpos($content, '=')) {
            parse_str($content, $data);
            return $data;
        }

        return [];
    }

    /**
     * 设置获取DELETE参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function delete($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * 设置获取PATCH参数
     * @access public
     * @param  mixed             $name 变量名
     * @param  mixed             $default 默认值
     * @param  string|array      $filter 过滤方法
     * @return mixed
     */
    public function patch($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * 获取request变量
     * @access public
     * @param  mixed         $name 数据名称
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function request($name = '', $default = null, $filter = '')
    {
        if (empty($this->request)) {
            $this->request = $_REQUEST;
        }

        if (is_array($name)) {
            return $this->only($name, $this->request, $filter);
        }

        return $this->input($this->request, $name, $default, $filter);
    }

    /**
     * 获取session数据
     * @access public
     * @param  string        $name 数据名称
     * @param  string        $default 默认值
     * @return mixed
     */
    public function session(string $name = '', $default = null)
    {
        if (empty($this->session)) {
            $this->session = Session::get();
        }

        if ('' === $name) {
            return $this->session;
        }

        $data = $this->getData($this->session, $name);

        return is_null($data) ? $default : $data;
    }

    /**
     * 获取cookie参数
     * @access public
     * @param  mixed         $name 数据名称
     * @param  string        $default 默认值
     * @param  string|array  $filter 过滤方法
     * @return mixed
     */
    public function cookie(string $name = '', $default = null, $filter = '')
    {
        if (empty($this->cookie)) {
            $this->cookie = Cookie::get();
        }

        if (!empty($name)) {
            $data = Cookie::has($name) ? Cookie::get($name) : $default;
        } else {
            $data = $this->cookie;
        }

        // 解析过滤器
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        return $data;
    }

    /**
     * 获取server参数
     * @access public
     * @param  string        $name 数据名称
     * @param  string        $default 默认值
     * @return mixed
     */
    public function server(string $name = '', string $default = null)
    {
        if (empty($name)) {
            return $this->server;
        } else {
            $name = strtoupper($name);
        }

        return $this->server[$name] ?? $default;
    }

    /**
     * 获取环境变量
     * @access public
     * @param  string        $name 数据名称
     * @param  string        $default 默认值
     * @return mixed
     */
    public function env(string $name = '', string $default = null)
    {
        if (empty($name)) {
            return $this->env;
        } else {
            $name = strtoupper($name);
        }

        return $this->env[$name] ?? $default;
    }

    /**
     * 获取上传的文件信息
     * @access public
     * @param  string|array $name 名称
     * @return null|array|\think\File
     */
    public function file($name = '')
    {
        if (empty($this->file)) {
            $this->file = $_FILES ?? [];
        }

        $files = $this->file;
        if (!empty($files)) {

            if (strpos($name, '.')) {
                list($name, $sub) = explode('.', $name);
            }

            // 处理上传文件
            $array = $this->dealUploadFile($files, $name);

            if ('' === $name) {
                // 获取全部文件
                return $array;
            } elseif (isset($sub) && isset($array[$name][$sub])) {
                return $array[$name][$sub];
            } elseif (isset($array[$name])) {
                return $array[$name];
            }
        }

        return;
    }

    protected function dealUploadFile($files, $name)
    {
        $array = [];
        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                $item  = [];
                $keys  = array_keys($file);
                $count = count($file['name']);

                for ($i = 0; $i < $count; $i++) {
                    if ($file['error'][$i] > 0) {
                        if ($name == $key) {
                            $this->throwUploadFileError($file['error'][$i]);
                        } else {
                            continue;
                        }
                    }

                    $temp['key'] = $key;

                    foreach ($keys as $_key) {
                        $temp[$_key] = $file[$_key][$i];
                    }

                    $item[] = (new File($temp['tmp_name']))->setUploadInfo($temp);
                }

                $array[$key] = $item;
            } else {
                if ($file instanceof File) {
                    $array[$key] = $file;
                } else {
                    if ($file['error'] > 0) {
                        if ($key == $name) {
                            $this->throwUploadFileError($file['error']);
                        } else {
                            continue;
                        }
                    }

                    $array[$key] = (new File($file['tmp_name']))->setUploadInfo($file);
                }
            }
        }

        return $array;
    }

    protected function throwUploadFileError($error)
    {
        static $fileUploadErrors = [
            1 => 'upload File size exceeds the maximum value',
            2 => 'upload File size exceeds the maximum value',
            3 => 'only the portion of file is uploaded',
            4 => 'no file to uploaded',
            6 => 'upload temp dir not found',
            7 => 'file write error',
        ];

        $msg = $fileUploadErrors[$error];
        throw new Exception($msg);
    }

    /**
     * 设置或者获取当前的Header
     * @access public
     * @param  string   $name header名称
     * @param  string   $default 默认值
     * @return string|array
     */
    public function header(string $name = '', string $default = null)
    {
        if (empty($this->header)) {
            $header = [];

            if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
                $header = $result;
            } else {
                $server = $this->server;
                foreach ($server as $key => $val) {
                    if (0 === strpos($key, 'HTTP_')) {
                        $key          = str_replace('_', '-', strtolower(substr($key, 5)));
                        $header[$key] = $val;
                    }
                }
                if (isset($server['CONTENT_TYPE'])) {
                    $header['content-type'] = $server['CONTENT_TYPE'];
                }
                if (isset($server['CONTENT_LENGTH'])) {
                    $header['content-length'] = $server['CONTENT_LENGTH'];
                }
            }

            $this->header = array_change_key_case($header);
        }

        if ('' === $name) {
            return $this->header;
        }

        $name = str_replace('_', '-', strtolower($name));

        return $this->header[$name] ?? $default;
    }

    /**
     * 获取变量 支持过滤和默认值
     * @access public
     * @param  array         $data 数据源
     * @param  string|false  $name 字段名
     * @param  mixed         $default 默认值
     * @param  string|array  $filter 过滤函数
     * @return mixed
     */
    public function input(array $data = [], $name = '', $default = null, $filter = '')
    {
        if (false === $name) {
            // 获取原始数据
            return $data;
        }

        $name = (string) $name;
        if ('' != $name) {
            // 解析name
            if (strpos($name, '/')) {
                list($name, $type) = explode('/', $name);
            }

            $data = $this->getData($data, $name);

            if (is_null($data)) {
                return $default;
            }

            if (is_object($data)) {
                return $data;
            }
        }

        return $this->filterData($data, $filter, $name, $default);
    }

    protected function filterData($data, $filter, $name, $default)
    {
        // 解析过滤器
        $filter = $this->getFilter($filter, $default);

        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        return $data;
    }

    /**
     * 获取数据
     * @access public
     * @param  array         $data 数据源
     * @param  string        $name 字段名
     * @return mixed
     */
    protected function getData(array $data, string $name)
    {
        foreach (explode('.', $name) as $val) {
            if (isset($data[$val])) {
                $data = $data[$val];
            } else {
                return;
            }
        }

        return $data;
    }

    /**
     * 设置或获取当前的过滤规则
     * @access public
     * @param  mixed $filter 过滤规则
     * @return mixed
     */
    public function filter($filter = null)
    {
        if (is_null($filter)) {
            return $this->filter;
        }

        $this->filter = $filter;

        return $this;
    }

    protected function getFilter($filter, $default)
    {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;

        return $filter;
    }

    /**
     * 递归过滤给定的值
     * @access public
     * @param  mixed     $value 键值
     * @param  mixed     $key 键名
     * @param  array     $filters 过滤方法+默认值
     * @return mixed
     */
    private function filterValue(&$value, $key, $filters)
    {
        $default = array_pop($filters);

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * 是否存在某个请求参数
     * @access public
     * @param  string    $name 变量名
     * @param  string    $type 变量类型
     * @param  bool      $checkEmpty 是否检测空值
     * @return bool
     */
    public function has(string $name, string $type = 'param', bool $checkEmpty = false): bool
    {
        $param = empty($this->$type) ? $this->$type() : $this->$type;

        // 按.拆分成多维数组进行判断
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }

        return ($checkEmpty && '' === $param) ? false : true;
    }

    /**
     * 获取指定的参数
     * @access public
     * @param  array            $name 变量名
     * @param  mixed            $data 数据或者变量类型
     * @param  string|array     $filter 过滤方法
     * @return array
     */
    public function only(array $name, $data = 'param', $filter = ''): array
    {
        $data = is_array($data) ? $data : $this->$data();

        $item = [];
        foreach ($name as $key => $val) {

            if (is_int($key)) {
                $default = null;
                $key     = $val;
                if (!isset($data[$key])) {
                    continue;
                }
            } else {
                $default = $val;
            }

            $item[$key] = $this->filterData($data[$key] ?? $default, $filter, $key, $default);
        }

        return $item;
    }

    /**
     * 排除指定参数获取
     * @access public
     * @param  array  $name 变量名
     * @param  string $type 变量类型
     * @return mixed
     */
    public function except(array $name, string $type = 'param'): array
    {
        $param = $this->$type();

        foreach ($name as $key) {
            if (isset($param[$key])) {
                unset($param[$key]);
            }
        }

        return $param;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl(): bool
    {
        if ($this->server('HTTPS') && ('1' == $this->server('HTTPS') || 'on' == strtolower($this->server('HTTPS')))) {
            return true;
        } elseif ('https' == $this->server('REQUEST_SCHEME')) {
            return true;
        } elseif ('443' == $this->server('SERVER_PORT')) {
            return true;
        } elseif ('https' == $this->server('HTTP_X_FORWARDED_PROTO')) {
            return true;
        } elseif ($this->config['https_agent_name'] && $this->server($this->config['https_agent_name'])) {
            return true;
        }

        return false;
    }

    /**
     * 当前是否Ajax请求
     * @access public
     * @param  bool $ajax  true 获取原始ajax请求
     * @return bool
     */
    public function isAjax(bool $ajax = false): bool
    {
        $value  = $this->server('HTTP_X_REQUESTED_WITH');
        $result = $value && 'xmlhttprequest' == strtolower($value) ? true : false;

        if (true === $ajax) {
            return $result;
        }

        return $this->param($this->config['var_ajax']) ? true : $result;
    }

    /**
     * 当前是否Pjax请求
     * @access public
     * @param  bool $pjax  true 获取原始pjax请求
     * @return bool
     */
    public function isPjax(bool $pjax = false): bool
    {
        $result = !is_null($this->server('HTTP_X_PJAX')) ? true : false;

        if (true === $pjax) {
            return $result;
        }

        return $this->param($this->config['var_pjax']) ? true : $result;
    }

    /**
     * 获取客户端IP地址
     * @access public
     * @return string
     */
    public function ip()
    {
        if ( ! empty($this->realIP)) {
            return $this->realIP;
        }

        $this->realIP = $this->server('REMOTE_ADDR');

        // 如果指定了前端代理服务器IP以及其会发送的IP头
        // 则尝试获取前端代理服务器发送过来的真实IP
        $proxy_server_ip = $this->config('proxy_server_ip');
        $proxy_server_ip_header = $this->config('proxy_server_ip_header');

        if ( ! empty($proxy_server_ip) && ! is_array($proxy_server_ip)) {
            $proxy_server_ip = explode(',', $proxy_server_ip);
            $proxy_server_ip = array_map('trim', $proxy_server_ip);
        }

        if (count($proxy_server_ip) > 0 && count($proxy_server_ip_header) > 0) {
            
            // 从指定的HTTP头中依次尝试获取IP地址
            // 直到获取到一个合法的IP地址
            foreach ($proxy_server_ip_header as $header) {
                $tempIP = $this->server($header);

                if (empty($tempIP)) {
                    continue;
                }

                $tempIP = trim(explode(',', $tempIP)[0]);

                if ( ! $this->isValidIP($tempIP)) {
                    $tempIP = null;
                }
                else {
                    break;
                }
            }

            // tempIP不为空，说明获取到了一个IP地址
            // 这时我们检查 REMOTE_ADDR 是不是指定的前端代理服务器之一
            // 如果是的话说明该 IP头 是由前端代理服务器设置的
            // 否则则是伪装的
            if ($tempIP) {
                $realIPBin = $this->ip2bin($this->realIP);

                foreach ($proxy_server_ip as $server_ip) {
                    $serverIPElements = explode('/', $server_ip);
                    $serverIP = $serverIPElements[0];
                    $serverIPPrefix = $serverIPElements[1] ?? 128;
                    $serverIPBin = $this->ip2bin($serverIP);

                    // IP类型不符
                    if (strlen($realIPBin) !== strlen($serverIPBin)) {
                        continue;
                    }

                    if (strncmp($realIPBin, $serverIPBin, $serverIPPrefix) === 0) {
                        $this->realIP = $tempIP;
                        break;
                    }
                }
            }
        }

        if ( ! $this->isValidIP($this->realIP)) {
            $this->realIP = '0.0.0.0';
        }

        return $this->realIP;
    }

    /**
     * 检测是否是合法的IP地址
     * 
     * @param string $ip IP地址
     * @param string $type IP地址类型 (ipv4, ipv6)
     * 
     * @return boolean
     */
    public function isValidIP(string $ip, string $type = null): bool
    {
        $type = strtolower($type);

        switch ($type) {
            case 'ipv4':
                $flag = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $flag = FILTER_FLAG_IPV6;
                break;
            default:
                $flag = null;
                break;
        }

        return boolval(filter_var($ip, FILTER_VALIDATE_IP, $flag));
    }

    /**
     * 将IP地址转换为二进制字符串
     * 
     * @param string $ip
     * 
     * @return string
     */
    public function ip2bin(string $ip): string
    {
        if ($this->isValidIP($ip, 'ipv6')) {
            $IPHex = str_split(bin2hex(inet_pton($ip)), 4);
            foreach ($IPHex as $key => $value) {
                $IPHex[$key] = intval($value, 16);
            }
            $IPBin = vsprintf('%016b%016b%016b%016b%016b%016b%016b%016b', $IPHex);
        }
        else {
            $IPHex = str_split(bin2hex(inet_pton($ip)), 2);
            foreach ($IPHex as $key => $value) {
                $IPHex[$key] = intval($value, 16);
            }
            $IPBin = vsprintf('%08b%08b%08b%08b', $IPHex);
        }

        return $IPBin;
    }

    /**
     * 检测是否使用手机访问
     * @access public
     * @return bool
     */
    public function isMobile(): bool
    {
        if ($this->server('HTTP_VIA') && stristr($this->server('HTTP_VIA'), "wap")) {
            return true;
        } elseif ($this->server('HTTP_ACCEPT') && strpos(strtoupper($this->server('HTTP_ACCEPT')), "VND.WAP.WML")) {
            return true;
        } elseif ($this->server('HTTP_X_WAP_PROFILE') || $this->server('HTTP_PROFILE')) {
            return true;
        } elseif ($this->server('HTTP_USER_AGENT') && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $this->server('HTTP_USER_AGENT'))) {
            return true;
        }

        return false;
    }

    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public function scheme(): string
    {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * 当前请求URL地址中的query参数
     * @access public
     * @return string
     */
    public function query(): string
    {
        return $this->server('QUERY_STRING');
    }

    /**
     * 设置当前请求的host（包含端口）
     * @access public
     * @param  string $host 主机名（含端口）
     * @return $this
     */
    public function setHost(string $host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * 当前请求的host
     * @access public
     * @param bool $strict  true 仅仅获取HOST
     * @return string
     */
    public function host(bool $strict = false): string
    {
        $host = strval($this->server('HTTP_X_REAL_HOST') ?: $this->server('HTTP_HOST'));

        return true === $strict && strpos($host, ':') ? strstr($host, ':', true) : $host;
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return string
     */
    public function port(): string
    {
        return $this->server('SERVER_PORT');
    }

    /**
     * 当前请求 SERVER_PROTOCOL
     * @access public
     * @return string
     */
    public function protocol(): string
    {
        return $this->server('SERVER_PROTOCOL');
    }

    /**
     * 当前请求 REMOTE_PORT
     * @access public
     * @return string
     */
    public function remotePort(): string
    {
        return $this->server('REMOTE_PORT');
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType(): string
    {
        $contentType = $this->server('CONTENT_TYPE');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
    }

    /**
     * 获取当前请求的路由信息
     * @access public
     * @param  array $route 路由名称
     * @return array
     */
    public function routeInfo(array $route = []): array
    {
        if (!empty($route)) {
            $this->routeInfo = $route;
        }

        return $this->routeInfo;
    }

    /**
     * 设置或者获取当前请求的调度信息
     * @access public
     * @param  Dispatch  $dispatch 调度信息
     * @return Dispatch
     */
    public function dispatch(Dispatch $dispatch = null)
    {
        if (!is_null($dispatch)) {
            $this->dispatch = $dispatch;
        }

        return $this->dispatch;
    }

    /**
     * 获取当前请求的安全Key
     * @access public
     * @return string
     */
    public function secureKey(): string
    {
        if (is_null($this->secureKey)) {
            $this->secureKey = uniqid('', true);
        }

        return $this->secureKey;
    }

    /**
     * 设置当前的应用名
     * @access public
     * @param  string $app 应用名
     * @return $this
     */
    public function setApp(string $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * 设置当前的控制器名
     * @access public
     * @param  string $controller 控制器名
     * @return $this
     */
    public function setController(string $controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * 设置当前的操作名
     * @access public
     * @param  string $action 操作名
     * @return $this
     */
    public function setAction(string $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * 获取当前的应用名
     * @access public
     * @return string
     */
    public function app(): string
    {
        return $this->app ?: '';
    }

    /**
     * 获取当前的控制器名
     * @access public
     * @param  bool $convert 转换为小写
     * @return string
     */
    public function controller(bool $convert = false): string
    {
        $name = $this->controller ?: '';
        return $convert ? strtolower($name) : $name;
    }

    /**
     * 获取当前的操作名
     * @access public
     * @param  bool $convert 转换为小写
     * @return string
     */
    public function action(bool $convert = false): string
    {
        $name = $this->action ?: '';
        return $convert ? strtolower($name) : $name;
    }

    /**
     * 设置当前的语言
     * @access public
     * @param  string $lang 语言名
     * @return $this
     */
    public function setLangset(string $lang)
    {
        $this->langset = $lang;
        return $this;
    }

    /**
     * 获取当前的语言
     * @access public
     * @return string
     */
    public function langset(): string
    {
        return $this->langset ?: '';
    }
    /**
     * 设置或者获取当前请求的content
     * @access public
     * @return string
     */
    public function getContent(): string
    {
        if (is_null($this->content)) {
            $this->content = $this->input;
        }

        return $this->content;
    }

    /**
     * 获取当前请求的php://input
     * @access public
     * @return string
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * 生成请求令牌
     * @access public
     * @param  string $name 令牌名称
     * @param  mixed  $type 令牌生成方法
     * @return string
     */
    public function token(string $name = '__token__', $type = 'md5'): string
    {
        $type  = is_callable($type) ? $type : 'md5';
        $token = call_user_func($type, $this->server('REQUEST_TIME_FLOAT'));

        if ($this->isAjax()) {
            header($name . ': ' . $token);
        }

        Container::pull('session')->set($name, $token);

        return $token;
    }

    /**
     * 设置当前地址的请求缓存
     * @access public
     * @param  string $key 缓存标识，支持变量规则 ，例如 item/:name/:id
     * @param  mixed  $expire 缓存有效期
     * @param  array  $except 缓存排除
     * @param  string $tag    缓存标签
     * @return mixed
     */
    public function cache($key, $expire = null, $except = [], $tag = null)
    {
        if (!is_array($except)) {
            $tag    = $except;
            $except = [];
        }

        if (false === $key || !$this->isGet() || $this->isCheckCache || false === $expire) {
            // 关闭当前缓存
            return;
        }

        // 标记请求缓存检查
        $this->isCheckCache = true;

        foreach ($except as $rule) {
            if (0 === stripos($this->url(), $rule)) {
                return;
            }
        }

        if ($key instanceof \Closure) {
            $key = call_user_func_array($key, [$this]);
        } elseif (true === $key) {
            // 自动缓存功能
            $key = '__URL__';
        } elseif (strpos($key, '|')) {
            list($key, $fun) = explode('|', $key);
        }

        // 特殊规则替换
        if (false !== strpos($key, '__')) {
            $key = str_replace(['__APP__', '__CONTROLLER__', '__ACTION__', '__URL__'], [$this->app, $this->controller, $this->action, md5($this->url(true))], $key);
        }

        if (false !== strpos($key, ':')) {
            $param = $this->param();
            foreach ($param as $item => $val) {
                if (is_string($val) && false !== strpos($key, ':' . $item)) {
                    $key = str_replace(':' . $item, $val, $key);
                }
            }
        } elseif (strpos($key, ']')) {
            if ('[' . $this->ext() . ']' == $key) {
                // 缓存某个后缀的请求
                $key = md5($this->url());
            } else {
                return;
            }
        }

        if (isset($fun)) {
            $key = $fun($key);
        }

        if (strtotime($this->server('HTTP_IF_MODIFIED_SINCE')) + $expire > $this->server('REQUEST_TIME')) {
            // 读取缓存
            $response = Response::create()->code(304);
            throw new HttpResponseException($response);
        } elseif ($this->app['cache']->has($key)) {
            list($content, $header) = $this->app['cache']->get($key);

            $response = Response::create($content)->header($header);
            throw new HttpResponseException($response);
        }

        $this->cache = [$key, $expire, $tag];
        return $this->cache;
    }

    /**
     * 读取请求缓存设置
     * @access public
     * @return array|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * 设置在中间件传递的数据
     * @access public
     * @param  array $middleware 数据
     * @return $this
     */
    public function withMiddleware(array $middleware)
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * 设置GET数据
     * @access public
     * @param  array $get 数据
     * @return $this
     */
    public function withGet(array $get)
    {
        $this->get = $get;
        return $this;
    }

    /**
     * 设置POST数据
     * @access public
     * @param  array $post 数据
     * @return $this
     */
    public function withPost(array $post)
    {
        $this->post = $post;
        return $this;
    }

    /**
     * 设置COOKIE数据
     * @access public
     * @param  array $cookie 数据
     * @return $this
     */
    public function withCookie(array $cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置SERVER数据
     * @access public
     * @param  array $server 数据
     * @return $this
     */
    public function withServer(array $server)
    {
        $this->server = array_change_key_case($server, CASE_UPPER);
        return $this;
    }

    /**
     * 设置HEADER数据
     * @access public
     * @param  array $header 数据
     * @return $this
     */
    public function withHeader(array $header)
    {
        $this->header = array_change_key_case($header);
        return $this;
    }

    /**
     * 设置ENV数据
     * @access public
     * @param  array $env 数据
     * @return $this
     */
    public function withEnv(array $env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * 设置php://input数据
     * @access public
     * @param  string $input RAW数据
     * @return $this
     */
    public function withInput(string $input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 设置文件上传数据
     * @access public
     * @param  array $files 上传信息
     * @return $this
     */
    public function withFiles(array $files)
    {
        $this->file = $files;
        return $this;
    }

    /**
     * 设置ROUTE变量
     * @access public
     * @param  array $route 数据
     * @return $this
     */
    public function withRoute(array $route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * 设置中间传递数据
     * @access public
     * @param  string    $name  参数名
     * @param  mixed     $value 值
     */
    public function __set(string $name, $value)
    {
        $this->middleware[$name] = $value;
    }

    /**
     * 获取中间传递数据的值
     * @access public
     * @param  string $name 名称
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->middleware($name);
    }

    /**
     * 检测请求数据的值
     * @access public
     * @param  string $name 名称
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        return isset($this->param[$name]);
    }
}
