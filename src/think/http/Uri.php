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

namespace think\http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
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
                $this->url = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
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
                $pathinfo = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
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
     * 从 URI 中取出 scheme。
     *
     * 如果不存在 Scheme，此方法 **必须** 返回空字符串。
     *
     * 根据 RFC 3986 规范 3.1 章节，返回的数据 **必须** 是小写字母。
     *
     * 最后部分的「:」字串不属于 Scheme，**不得** 作为返回数据的一部分。
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.1
     * @return string URI Ccheme 的值。
     */
    public function getScheme(){
        return $this->scheme();
    }

    /**
     * 返回 URI 认证信息。
     *
     * 如果没有 URI 认证信息的话，**必须** 返回一个空字符串。
     *
     * URI 的认证信息语法是：
     *
     * <pre>
     * [user-info@]host[:port]
     * </pre>
     *
     * 如果端口部分没有设置，或者端口是标准端口，**不应该** 包含在返回值内。
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2
     * @return string URI 认证信息，格式为：「[user-info@]host[:port]」。
     */
    public function getAuthority(){
        return 
    }

    /**
     * 从 URI 中获取用户信息。
     *
     * 如果不存在用户信息，此方法 **必须** 返回一个空字符串。
     * 
     * 如果 URI 中存在用户，则返回该值；此外，如果密码也存在，它将附加到用户值，用冒号（「:」）分隔。
     *
     * 用户信息后面跟着的 "@" 字符，不是用户信息里面的一部分，**不得** 在返回值里出现。
     *
     * @return string URI 的用户信息，格式："username[:password]" 
     */
    public function getUserInfo();

    /**
     * 从 URI 中获取 HOST 信息。
     *
     * 如果 URI 中没有此值，**必须** 返回空字符串。
     *
     * 根据 RFC 3986 规范 3.2.2 章节，返回的数据 **必须** 是小写字母。
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     * @return string URI 中的 HOST 信息。
     */
    public function getHost();

    /**
     * 从 URI 中获取端口信息。
     *
     * 如果端口信息是与当前 Scheme 的标准端口不匹配的话，就使用整数值的格式返回，如果是一
     * 样的话，**应该** 返回 `null` 值。
     * 
     * 如果不存在端口和 Scheme 信息，**必须** 返回 `null` 值。
     * 
     * 如果不存在端口数据，但是存在 Scheme 的话，**可能** 返回 Scheme 对应的
     * 标准端口，但是 **应该** 返回 `null`。
     * 
     * @return null|int URI 中的端口信息。
     */
    public function getPort();

    /**
     * 从 URI 中获取路径信息。
     *
     * 路径可以是空的，或者是绝对的（以斜线「/」开头），或者相对路径（不以斜线开头）。
     * 实现 **必须** 支持所有三种语法。
     *
     * 根据 RFC 7230 第 2.7.3 节，通常空路径「」和绝对路径「/」被认为是相同的。
     * 但是这个方法 **不得** 自动进行这种规范化，因为在具有修剪的基本路径的上下文中，
     * 例如前端控制器中，这种差异将变得显著。用户的任务就是可以将「」和「/」都处理好。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.3 节。
     *
     * 例如，如果值包含斜线（「/」）而不是路径段之间的分隔符，则该值必须以编码形式（例如「%2F」）
     * 传递给实例。
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     * @return string URI 路径信息。
     */
    public function getPath();

    /**
     * 获取 URI 中的查询字符串。
     *
     * 如果不存在查询字符串，则此方法必须返回空字符串。
     *
     * 前导的「?」字符不是查询字符串的一部分，**不得** 添加在返回值中。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.4 节。
     *
     * 例如，如果查询字符串的键值对中的值包含不做为值之间分隔符的（「&」），则该值必须
     * 以编码形式传递（例如「%26」）到实例。
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.4
     * @return string URI 中的查询字符串
     */
    public function getQuery();

    /**
     * 获取 URI 中的片段（Fragment）信息。
     *
     * 如果没有片段信息，此方法 **必须** 返回空字符串。
     *
     * 前导的「#」字符不是片段的一部分，**不得** 添加在返回值中。
     *
     * 返回的值 **必须** 是百分号编码，但 **不得** 对任何字符进行双重编码。
     * 要确定要编码的字符，请参阅 RFC 3986 第 2 节和第 3.5 节。
     *
     * @see https://tools.ietf.org/html/rfc3986#section-2
     * @see https://tools.ietf.org/html/rfc3986#section-3.5
     * @return string URI 中的片段信息。
     */
    public function getFragment();

    /**
     * 返回具有指定 Scheme 的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定 Scheme 的实例。
     *
     * 实现 **必须** 支持大小写不敏感的「http」和「https」的 Scheme，并且在
     * 需要的时候 **可能** 支持其他的 Scheme。
     *
     * 空的 Scheme 相当于删除 Scheme。
     *
     * @param string $scheme 给新实例使用的 Scheme。
     * @return self 具有指定 Scheme 的新实例。
     * @throws \InvalidArgumentException 使用无效的 Scheme 时抛出。
     * @throws \InvalidArgumentException 使用不支持的 Scheme 时抛出。
     */
    public function withScheme($scheme);

    /**
     * 返回具有指定用户信息的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定用户信息的实例。
     *
     * 密码是可选的，但用户信息 **必须** 包括用户；用户信息的空字符串相当于删除用户信息。
     * 
     * @param string $user 用于认证的用户名。
     * @param null|string $password 密码。
     * @return self 具有指定用户信息的新实例。
     */
    public function withUserInfo($user, $password = null);

    /**
     * 返回具有指定 HOST 信息的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定 HOST 信息的实例。
     *
     * 空的 HOST 信息等同于删除 HOST 信息。
     *
     * @param string $host 用于新实例的 HOST 信息。
     * @return self 具有指定 HOST 信息的实例。
     * @throws \InvalidArgumentException 使用无效的 HOST 信息时抛出。
     */
    public function withHost($host);

    /**
     * 返回具有指定端口的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定端口的实例。
     *
     * 实现 **必须** 为已建立的 TCP 和 UDP 端口范围之外的端口引发异常。
     *
     * 为端口提供的空值等同于删除端口信息。
     *
     * @param null|int $port 用于新实例的端口；`null` 值将删除端口信息。
     * @return self 具有指定端口的实例。
     * @throws \InvalidArgumentException 使用无效端口时抛出异常。
     */
    public function withPort($port);

    /**
     * 返回具有指定路径的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含指定路径的实例。
     *
     * 路径可以是空的、绝对的（以斜线开头）或者相对路径（不以斜线开头），实现必须支持这三种语法。
     *
     * 如果 HTTP 路径旨在与 HOST 相对而不是路径相对，，那么它必须以斜线开头。
     * 假设 HTTP 路径不以斜线开头，对应该程序或开发人员来说，相对于一些已知的路径。
     *
     * 用户可以提供编码和解码的路径字符，要确保实现了 `getPath()` 中描述的正确编码。
     *
     * @param string $path 用于新实例的路径。
     * @return self 具有指定路径的实例。
     * @throws \InvalidArgumentException 使用无效的路径时抛出。
     */
    public function withPath($path);

    /**
     * 返回具有指定查询字符串的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含查询字符串的实例。
     *
     * 用户可以提供编码和解码的查询字符串，要确保实现了 `getQuery()` 中描述的正确编码。
     *
     * 空查询字符串值等同于删除查询字符串。
     *
     * @param string $query 用于新实例的查询字符串。
     * @return self 具有指定查询字符串的实例。
     * @throws \InvalidArgumentException 使用无效的查询字符串时抛出。
     */
    public function withQuery($query);

    /**
     * 返回具有指定 URI 片段（Fragment）的实例。
     *
     * 此方法 **必须** 保留当前实例的状态，并返回包含片段的实例。
     *
     * 用户可以提供编码和解码的片段，要确保实现了 `getFragment()` 中描述的正确编码。
     *
     * 空片段值等同于删除片段。
     *
     * @param string $fragment 用于新实例的片段。
     * @return self 具有指定 URI 片段的实例。
     */
    public function withFragment($fragment);

    /**
     * 返回字符串表示形式的 URI。
     *
     * 根据 RFC 3986 第 4.1 节，结果字符串是完整的 URI 还是相对引用，取决于 URI 有哪些组件。
     * 该方法使用适当的分隔符连接 URI 的各个组件：
     *
     * - 如果存在 Scheme 则 **必须** 以「:」为后缀。
     * - 如果存在认证信息，则必须以「//」作为前缀。
     * - 路径可以在没有分隔符的情况下连接。但是有两种情况需要调整路径以使 URI 引用有效，因为 PHP
     *   不允许在 `__toString()` 中引发异常：
     *     - 如果路径是相对的并且有认证信息，则路径 **必须** 以「/」为前缀。
     *     - 如果路径以多个「/」开头并且没有认证信息，则起始斜线 **必须** 为一个。
     * - 如果存在查询字符串，则 **必须** 以「?」作为前缀。
     * - 如果存在片段（Fragment），则 **必须** 以「#」作为前缀。
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.1
     * @return string
     */
    public function __toString();

}
