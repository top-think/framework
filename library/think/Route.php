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

use think\exception\RouteNotFoundException;
use think\route\dispatch\Url as UrlDispatch;
use think\route\Domain;
use think\route\Resource;
use think\route\RuleGroup;
use think\route\RuleItem;

class Route
{
    // REST路由操作方法定义
    protected $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    // 不同请求类型的方法前缀
    protected $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    // 当前配置实例
    protected $config;
    // 当前请求对象
    protected $request;
    // 当前域名
    protected $domain;
    // 当前分组
    protected $group;
    // 路由标识
    protected $name = [];
    // 路由绑定
    protected $bind = [];
    // 域名对象
    protected $domains = [];
    // 跨域路由规则
    protected $cross;
    // 当前路由标识
    protected $ruleName;
    // 别名路由
    protected $alias = [];

    public function __construct(Request $request, Config $config)
    {
        $this->config  = $config;
        $this->request = $request;
        $this->host    = $this->request->host();

        $this->setDefaultDomain();
    }

    /**
     * 初始化默认域名
     * @access protected
     * @return void
     */
    protected function setDefaultDomain()
    {
        // 默认域名
        $this->domain = $this->host;

        // 注册默认域名
        $domain = new Domain($this, $this->host);

        $this->domains[$this->host] = $domain;

        // 默认分组
        $this->group = $this->createTopGroup($domain);
    }

    /**
     * 创建一个域名下的顶级路由分组
     * @access protected
     * @param Domain    $domain 域名
     * @return RuleGroup
     */
    protected function createTopGroup(Domain $domain)
    {
        $group = new RuleGroup($this);
        // 注册分组到当前域名
        $domain->addRule($group);

        return $group;
    }

    /**
     * 设置当前域名
     * @access public
     * @param RuleGroup    $group 域名
     * @return void
     */
    public function setGroup(RuleGroup $group)
    {
        $this->group = $group;
    }

    /**
     * 获取当前分组
     * @access public
     * @return RuleGroup
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * 注册变量规则
     * @access public
     * @param string|array  $name 变量名
     * @param string        $rule 变量规则
     * @return $this
     */
    public function pattern($name, $rule = '')
    {
        $this->group->pattern($name, $rule);

        return $this;
    }

    /**
     * 注册路由参数
     * @access public
     * @param string|array  $name  参数名
     * @param mixed         $value 值
     * @return $this
     */
    public function option($name, $value = '')
    {
        $this->group->option($name, $value);

        return $this;
    }

    /**
     * 注册域名路由
     * @access public
     * @param string|array  $name 子域名
     * @param mixed         $rule 路由规则
     * @param array         $option 路由参数
     * @param array         $pattern 变量规则
     * @return Domain
     */
    public function domain($name, $rule = '', $option = [], $pattern = [])
    {
        // 支持多个域名使用相同路由规则
        $domain = is_array($name) ? array_shift($name) : $name;

        if (!strpos($domain, '.')) {
            $root = $this->config->get('app.url_domain_root');
            if (!$root) {
                $item  = explode('.', $this->host);
                $count = count($item);
                $root  = $count > 1 ? $item[$count - 2] . '.' . $item[$count - 1] : $item[0];
            }
            $domain .= '.' . $root;
        }

        $route = $this->config->get('url_lazy_route') ? $rule : null;

        $this->domains[$domain] = new Domain($this, $domain, $route, $option, $pattern);

        if (is_null($route)) {
            // 获取原始分组
            $originGroup = $this->group;
            // 设置当前域名
            $this->domain = $domain;
            $this->group  = $this->createTopGroup($this->domains[$domain]);

            // 执行域名路由
            if ($rule instanceof \Closure) {
                Container::getInstance()->invokeFunction($rule);
            } elseif (is_array($rule)) {
                $this->rules($rule);
            } elseif ($rule) {
                $this->bind($rule);
            }

            // 还原默认域名
            $this->domain = $this->host;
            // 还原默认分组
            $this->group = $originGroup;
        }

        if (is_array($name) && !empty($name)) {
            foreach ($name as $item) {
                if (!strpos($item, '.')) {
                    $item .= '.' . $root;
                }

                $this->domains[$item] = $domain;
            }
        }

        // 返回域名对象
        return $this->domains[$domain];
    }

    /**
     * 获取域名
     * @access public
     * @return array
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * 设置路由绑定
     * @access public
     * @param string     $bind 绑定信息
     * @return $this
     */
    public function bind($bind)
    {
        $this->bind[$this->domain] = $bind;

        return $this;
    }

    /**
     * 读取路由绑定
     * @access public
     * @param string    $domain 域名
     * @return string|null
     */
    public function getBind($domain = null)
    {
        if (is_null($domain)) {
            $domain = $this->domain;
        }

        return isset($this->bind[$domain]) ? $this->bind[$domain] : null;
    }

    /**
     * 设置当前路由标识
     * @access public
     * @param string     $name 路由命名标识
     * @return $this
     */
    public function name($name)
    {
        $this->ruleName = $name;

        return $this;
    }

    /**
     * 读取路由标识
     * @access public
     * @param string    $name 路由标识
     * @return array|null
     */
    public function getName($name = null)
    {
        if (is_null($name)) {
            return $this->name;
        }

        $name = strtolower($name);

        return isset($this->name[$name]) ? $this->name[$name] : null;
    }

    /**
     * 批量导入路由标识
     * @access public
     * @param array    $name 路由标识
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * 导入配置文件的路由规则
     * @access public
     * @param array     $rules 路由规则
     * @param string    $type  请求类型
     * @return void
     */
    public function import(array $rules, $type = '*')
    {
        // 检查域名部署
        if (isset($rules['__domain__'])) {
            foreach ($rules['__domain__'] as $key => $rule) {
                $this->domain($key, $rule);
            }
            unset($rules['__domain__']);
        }

        // 检查变量规则
        if (isset($rules['__pattern__'])) {
            $this->pattern($rules['__pattern__']);
            unset($rules['__pattern__']);
        }

        // 检查路由别名
        if (isset($rules['__alias__'])) {
            $this->alias($rules['__alias__']);
            unset($rules['__alias__']);
        }

        // 检查资源路由
        if (isset($rules['__rest__'])) {
            foreach ($rules['__rest__'] as $key => $rule) {
                $this->resource($key, $rule);
            }
            unset($rules['__rest__']);
        }

        // 检查路由规则（包含分组）
        foreach ($rules as $key => $val) {
            if (is_numeric($key)) {
                $key = array_shift($val);
            }

            if (empty($val)) {
                continue;
            }

            if (is_string($key) && 0 === strpos($key, '[')) {
                $key = substr($key, 1, -1);
                $this->group($key, $val);
            } elseif (is_array($val)) {
                $this->rule($key, $val[0], $type, $val[1], isset($val[2]) ? $val[2] : []);
            } else {
                $this->rule($key, $val, $type);
            }
        }
    }

    /**
     * 注册路由规则
     * @access public
     * @param string    $rule       路由规则
     * @param mixed     $route      路由地址
     * @param string    $method     请求类型
     * @param array     $option     路由参数
     * @param array     $pattern    变量规则
     * @return RuleItem
     */
    public function rule($rule, $route, $method = '*', $option = [], $pattern = [])
    {
        // 读取路由标识
        if (is_array($rule)) {
            list($name, $rule) = $rule;
        } elseif ($this->ruleName) {
            $name = $this->ruleName;

            $this->ruleName = null;
        } elseif (is_string($route)) {
            $name = $route;
        }

        $method = strtolower($method);

        // 当前分组名
        $group = $this->group->getName();
        if ($group) {
            $rule = $group . '/' . $rule;
        }

        if (isset($name)) {
            // 设置路由标识 用于URL快速生成
            $this->setRuleName($rule, $name, $option);
        }

        // 创建路由规则实例
        $rule = new RuleItem($this, $this->group, $rule, $route, $method, $option, $pattern);

        // 添加到当前分组
        $this->group->addRule($rule, $method);

        if (!empty($option['cross_domain'])) {
            $this->setCrossDomainRule($rule, $method);
        }

        return $rule;
    }

    /**
     * 设置路由标识 用于URL反解生成
     * @access public
     * @param string    $rule      路由规则
     * @param string    $name      路由标识
     * @param array     $option    路由参数
     * @return void
     */
    protected function setRuleName($rule, $name, $option = [])
    {
        $vars = $this->parseVar($rule);

        if (isset($option['ext'])) {
            $suffix = $option['ext'];
        } elseif ($this->group->getOption('ext')) {
            $suffix = $this->group->getOption('ext');
        } else {
            $suffix = null;
        }

        $this->name[strtolower($name)][] = [$rule, $vars, $this->domain, $suffix];
    }

    /**
     * 设置跨域有效路由规则
     * @access public
     * @param Rule      $rule      路由规则
     * @param string    $method    请求类型
     * @return $this
     */
    public function setCrossDomainRule($rule, $method = '*')
    {
        if (!isset($this->cross)) {
            $this->cross = new RuleGroup($this);
        }

        $this->cross->addRule($rule, $method);

        return $this;
    }

    /**
     * 批量注册路由规则
     * @access public
     * @param string    $rules      路由规则
     * @param string    $method     请求类型
     * @param array     $option     路由参数
     * @param array     $pattern    变量规则
     * @return void
     */
    public function rules($rules, $method = '*', $option = [], $pattern = [])
    {
        foreach ($rules as $key => $val) {
            if (is_numeric($key)) {
                $key = array_shift($val);
            }

            if (is_array($val)) {
                $route   = array_shift($val);
                $option  = $val ? array_shift($val) : [];
                $pattern = $val ? array_shift($val) : [];
            } else {
                $route = $val;
            }

            $this->rule($key, $route, $method, $option, $pattern);
        }
    }

    /**
     * 注册路由分组
     * @access public
     * @param string|array      $name       分组名称或者参数
     * @param array|\Closure    $route      分组路由
     * @param array             $option     路由参数
     * @param array             $pattern    变量规则
     * @return RuleGroup
     */
    public function group($name, $route, $option = [], $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }

        // 创建分组实例
        $rule  = $this->config->get('url_lazy_route') ? $route : null;
        $group = new RuleGroup($this, $this->group, $name, $rule, $option, $pattern);

        if (is_null($rule)) {
            // 解析分组路由
            $parent = $this->getGroup();

            $this->group = $group;

            if ($route instanceof \Closure) {
                Container::getInstance()->invokeFunction($route);
            } elseif ($route instanceof Response) {
                $group->setRule($route);
            } else {
                $this->rules($route);
            }

            $this->group = $parent;
        }

        // 注册子分组
        $this->group->addRule($group);

        if (!empty($option['cross_domain'])) {
            $this->setCrossDomainRule($group);
        }

        return $group;
    }

    /**
     * 注册路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function any($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, '*', $option, $pattern);
    }

    /**
     * 注册GET路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function get($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, 'GET', $option, $pattern);
    }

    /**
     * 注册POST路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function post($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, 'POST', $option, $pattern);
    }

    /**
     * 注册PUT路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function put($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, 'PUT', $option, $pattern);
    }

    /**
     * 注册DELETE路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function delete($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, 'DELETE', $option, $pattern);
    }

    /**
     * 注册PATCH路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return RuleItem
     */
    public function patch($rule, $route = '', $option = [], $pattern = [])
    {
        return $this->rule($rule, $route, 'PATCH', $option, $pattern);
    }

    /**
     * 注册资源路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return Resource
     */
    public function resource($rule, $route = '', $option = [], $pattern = [])
    {
        $resource = new Resource($this, $this->group, $rule, $route, $option, $pattern, $this->rest);

        // 添加到当前分组
        $this->group->addRule($resource);

        return $resource;
    }

    /**
     * 注册控制器路由 操作方法对应不同的请求后缀
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return $this
     */
    public function controller($rule, $route = '', $option = [], $pattern = [])
    {
        foreach ($this->methodPrefix as $type => $val) {
            $this->$type($rule . '/:action', $route . '/' . $val . ':action', $option, $pattern);
        }

        return $this;
    }

    /**
     * 注册别名路由
     * @access public
     * @param string|array  $rule 路由别名
     * @param string        $route 路由地址
     * @param array         $option 路由参数
     * @return $this
     */
    public function alias($rule = null, $route = '', $option = [])
    {
        if (is_array($rule)) {
            $this->alias = array_merge($this->alias, $rule);
        } else {
            $this->alias[$rule] = $option ? [$route, $option] : $route;
        }

        return $this;
    }

    /**
     * 获取别名路由定义
     * @access public
     * @param string    $name 路由别名
     * @return string|array|null
     */
    public function getAlias($name = null)
    {
        if (is_null($name)) {
            return $this->alias;
        }

        return isset($this->alias[$name]) ? $this->alias[$name] : null;
    }

    /**
     * 设置不同请求类型下面的方法前缀
     * @access public
     * @param string|array  $method 请求类型
     * @param string        $prefix 类型前缀
     * @return $this
     */
    public function setMethodPrefix($method, $prefix = '')
    {
        if (is_array($method)) {
            $this->methodPrefix = array_merge($this->methodPrefix, array_change_key_case($method));
        } else {
            $this->methodPrefix[strtolower($method)] = $prefix;
        }

        return $this;
    }

    /**
     * 获取请求类型的方法前缀
     * @access public
     * @param string    $method 请求类型
     * @param string    $prefix 类型前缀
     * @return string|null
     */
    public function getMethodPrefix($method)
    {
        $method = strtolower($method);

        return isset($this->methodPrefix[$method]) ? $this->methodPrefix[$method] : null;
    }

    /**
     * rest方法定义和修改
     * @access public
     * @param string        $name 方法名称
     * @param array|bool    $resource 资源
     * @return $this
     */
    public function rest($name, $resource = [])
    {
        if (is_array($name)) {
            $this->rest = $resource ? $name : array_merge($this->rest, $name);
        } else {
            $this->rest[$name] = $resource;
        }

        return $this;
    }

    /**
     * 获取rest方法定义的参数
     * @access public
     * @param string        $name 方法名称
     * @return array|null
     */
    public function getRest($name = null)
    {
        if (is_null($name)) {
            return $this->rest;
        }

        return isset($this->rest[$name]) ? $this->rest[$name] : null;
    }

    /**
     * 注册未匹配路由规则后的处理
     * @access public
     * @param string    $route 路由地址
     * @param string    $method 请求类型
     * @param array     $option 路由参数
     * @return RuleItem
     */
    public function miss($route, $method = '*', $option = [])
    {
        return $this->rule('__miss__', $route, $method, $option);
    }

    /**
     * 注册一个自动解析的URL路由
     * @access public
     * @param string    $route 路由地址
     * @return RuleItem
     */
    public function auto($route)
    {
        return $this->rule('__auto__', $route);
    }

    /**
     * 检测URL路由
     * @access public
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $must 是否强制路由
     * @param bool      $completeMatch   路由是否完全匹配
     * @return Dispatch
     * @throws RouteNotFoundException
     */
    public function check($url, $depr = '/', $must = false, $completeMatch = false)
    {
        // 自动检测域名路由
        $domain = $this->checkDomain();
        $url    = str_replace($depr, '|', $url);

        $result = $domain->check($this->request, $url, $depr, $completeMatch);

        if (false === $result && !empty($this->cross)) {
            // 检测跨越路由
            $result = $this->cross->check($this->request, $url, $depr, $completeMatch);
        }

        if (false !== $result) {
            // 路由匹配
            return $result;
        } elseif ($must) {
            // 强制路由不匹配则抛出异常
            throw new RouteNotFoundException();
        } else {
            // 默认路由解析
            return new UrlDispatch($url, ['depr' => $depr, 'auto_search' => $this->config->get('app.controller_auto_search')]);
        }
    }

    /**
     * 检测域名的路由规则
     * @access public
     * @param string    $host 当前主机地址
     * @return Domain
     */
    protected function checkDomain()
    {
        // 获取当前主域名
        $rootDomain = $this->config->get('app.url_domain_root');

        if ($rootDomain) {
            // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
            $domain = explode('.', rtrim(stristr($this->host, $rootDomain, true), '.'));
        } else {
            $domain = explode('.', $this->host, -2);
        }

        $item = false;

        if (!empty($domain) && count($this->domains) > 1) {
            // 当前子域名
            $subDomain = implode('.', $domain);
            $domain2   = array_pop($domain);

            if ($domain) {
                // 存在三级域名
                $domain3 = array_pop($domain);
            }

            if ($subDomain && isset($this->domains[$subDomain])) {
                // 子域名配置
                $item = $this->domains[$subDomain];
            } elseif (isset($this->domains['*.' . $domain2]) && !empty($domain3)) {
                // 泛三级域名
                $item      = $this->domains['*.' . $domain2];
                $panDomain = $domain3;
            } elseif (isset($this->domains['*']) && !empty($domain2)) {
                // 泛二级域名
                if ('www' != $domain2) {
                    $item      = $this->domains['*'];
                    $panDomain = $domain2;
                }
            }

            if (isset($panDomain)) {
                // 保存当前泛域名
                $this->request->route(['__domain__' => $panDomain]);
            }
        }

        if (false === $item) {
            // 检测当前完整域名
            $item = $this->domains[$this->host];
        } elseif (is_string($item)) {
            $item = $this->domains[$item];
        }

        return $item;
    }

    /**
     * 分析路由规则中的变量
     * @access public
     * @param string    $rule 路由规则
     * @return array
     */
    public function parseVar($rule)
    {
        // 提取路由规则中的变量
        $var = [];

        foreach (explode('/', $rule) as $val) {
            $optional = false;

            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name     = substr($name, 0, -1);
                        $optional = true;
                    } else {
                        $optional = false;
                    }
                    $var[$name] = $optional ? 2 : 1;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $optional = true;
                $val      = substr($val, 1, -1);
            }

            if (0 === strpos($val, ':')) {
                // URL变量
                $name = substr($val, 1);
                if ('$' == substr($name, -1)) {
                    $name = substr($name, 0, -1);
                }

                $var[$name] = $optional ? 2 : 1;
            }
        }

        return $var;
    }

    /**
     * 设置全局的路由分组参数
     * @access protected
     * @param string    $method     方法名
     * @param array     $args       调用参数
     * @return RuleGroup
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->group, $method], $args);
    }
}
