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

namespace think;

use think\exception\RouteNotFoundException;
use think\route\AliasRule;
use think\route\dispatch\Url as UrlDispatch;
use think\route\Domain;
use think\route\Resource;
use think\route\RuleGroup;
use think\route\RuleItem;

class Route
{
    /**
     * REST定义
     * @var array
     */
    protected $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    /**
     * 请求方法前缀定义
     * @var array
     */
    protected $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    /**
     * 配置对象
     * @var Config
     */
    protected $config;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 当前HOST
     * @var string
     */
    protected $host;

    /**
     * 当前域名
     * @var string
     */
    protected $domain;

    /**
     * 当前分组对象
     * @var RuleGroup
     */
    protected $group;

    /**
     * 路由绑定
     * @var array
     */
    protected $bind = [];

    /**
     * 域名对象
     * @var array
     */
    protected $domains = [];

    /**
     * 跨域路由规则
     * @var RuleGroup
     */
    protected $cross;

    /**
     * 路由别名
     * @var array
     */
    protected $alias = [];

    /**
     * 路由是否延迟解析
     * @var bool
     */
    protected $lazy = true;

    public function __construct(Request $request, Config $config)
    {
        $this->config  = $config;
        $this->request = $request;
        $this->host    = $this->request->host();

        $this->setDefaultDomain();
    }

    /**
     * 设置路由域名及分组（包括资源路由）是否延迟解析
     * @access public
     * @param  bool     $lazy   路由是否延迟解析
     * @return $this
     */
    public function lazy($lazy = true)
    {
        $this->lazy = $lazy;
        return $this;
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
        $this->group = $domain;
    }

    /**
     * 设置当前域名
     * @access public
     * @param  RuleGroup    $group 域名
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
     * @param  string|array  $name 变量名
     * @param  string        $rule 变量规则
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
     * @param  string|array  $name  参数名
     * @param  mixed         $value 值
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
     * @param  string|array  $name 子域名
     * @param  mixed         $rule 路由规则
     * @param  array         $option 路由参数
     * @param  array         $pattern 变量规则
     * @return Domain
     */
    public function domain($name, $rule = '', $option = [], $pattern = [])
    {
        // 支持多个域名使用相同路由规则
        $domainName = is_array($name) ? array_shift($name) : $name;

        if ('*' != $domainName && !strpos($domainName, '.')) {
            $domainName .= '.' . $this->request->rootDomain();
        }

        if (!isset($this->domains[$domainName])) {
            $domain = (new Domain($this, $domainName, $rule, $option, $pattern))
                ->lazy($this->lazy);
            $this->domains[$domainName] = $domain;
        } else {
            $domain = $this->domains[$domainName];
            $domain->parseGroupRule($rule);
        }

        if (is_array($name) && !empty($name)) {
            $root = $this->request->rootDomain();
            foreach ($name as $item) {
                if (!strpos($item, '.')) {
                    $item .= '.' . $root;
                }

                $this->domains[$item] = $domainName;
            }
        }

        // 返回域名对象
        return $domain;
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
     * @param  string     $bind 绑定信息
     * @param  string     $domain 域名
     * @return $this
     */
    public function bind($bind, $domain = null)
    {
        $domain = is_null($domain) ? $this->domain : $domain;

        $this->bind[$domain] = $bind;

        return $this;
    }

    /**
     * 读取路由绑定
     * @access public
     * @param  string    $domain 域名
     * @return string|null
     */
    public function getBind($domain = null)
    {
        if (is_null($domain)) {
            $domain = $this->domain;
        }

        $subDomain = $this->request->subDomain();

        if (strpos($subDomain, '.')) {
            $name = '*' . strstr($subDomain, '.');
        }

        if (isset($this->bind[$domain])) {
            $result = $this->bind[$domain];
        } elseif (isset($name) && isset($this->bind[$name])) {
            $result = $this->bind[$name];
        } elseif (isset($this->bind['*'])) {
            $result = $this->bind['*'];
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * 读取路由标识
     * @access public
     * @param  string    $name 路由标识
     * @return mixed
     */
    public function getName($name = null)
    {
        return Container::get('rule_name')->get($name);
    }

    /**
     * 批量导入路由标识
     * @access public
     * @param  array    $name 路由标识
     * @return $this
     */
    public function setName($name)
    {
        Container::get('rule_name')->import($name);
        return $this;
    }

    /**
     * 导入配置文件的路由规则
     * @access public
     * @param  array     $rules 路由规则
     * @param  string    $type  请求类型
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
     * @param  string    $rule       路由规则
     * @param  mixed     $route      路由地址
     * @param  string    $method     请求类型
     * @param  array     $option     路由参数
     * @param  array     $pattern    变量规则
     * @return RuleItem
     */
    public function rule($rule, $route, $method = '*', array $option = [], array $pattern = [])
    {
        return $this->group->addRule($rule, $route, $method, $option, $pattern);
    }

    /**
     * 设置跨域有效路由规则
     * @access public
     * @param  Rule      $rule      路由规则
     * @param  string    $method    请求类型
     * @return $this
     */
    public function setCrossDomainRule($rule, $method = '*')
    {
        if (!isset($this->cross)) {
            $this->cross = new RuleGroup($this);
        }

        $this->cross->addRuleItem($rule, $method);

        return $this;
    }

    /**
     * 批量注册路由规则
     * @access public
     * @param  array     $rules      路由规则
     * @param  string    $method     请求类型
     * @param  array     $option     路由参数
     * @param  array     $pattern    变量规则
     * @return void
     */
    public function rules($rules, $method = '*', array $option = [], array $pattern = [])
    {
        $this->group->addRules($rules, $method, $option, $pattern);
    }

    /**
     * 注册路由分组
     * @access public
     * @param  string|array      $name       分组名称或者参数
     * @param  array|\Closure    $route      分组路由
     * @param  array             $option     路由参数
     * @param  array             $pattern    变量规则
     * @return RuleGroup
     */
    public function group($name, $route, array $option = [], array $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }

        return (new RuleGroup($this, $this->group, $name, $route, $option, $pattern))
            ->lazy($this->lazy);
    }

    /**
     * 注册路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function any($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, '*', $option, $pattern);
    }

    /**
     * 注册GET路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function get($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, 'GET', $option, $pattern);
    }

    /**
     * 注册POST路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function post($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, 'POST', $option, $pattern);
    }

    /**
     * 注册PUT路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function put($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, 'PUT', $option, $pattern);
    }

    /**
     * 注册DELETE路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function delete($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, 'DELETE', $option, $pattern);
    }

    /**
     * 注册PATCH路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  mixed     $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleItem
     */
    public function patch($rule, $route = '', array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, 'PATCH', $option, $pattern);
    }

    /**
     * 注册资源路由
     * @access public
     * @param  string    $rule 路由规则
     * @param  string    $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return Resource
     */
    public function resource($rule, $route = '', array $option = [], array $pattern = [])
    {
        return (new Resource($this, $this->group, $rule, $route, $option, $pattern, $this->rest))
            ->lazy($this->lazy);
    }

    /**
     * 注册控制器路由 操作方法对应不同的请求前缀
     * @access public
     * @param  string    $rule 路由规则
     * @param  string    $route 路由地址
     * @param  array     $option 路由参数
     * @param  array     $pattern 变量规则
     * @return RuleGroup
     */
    public function controller($rule, $route = '', array $option = [], array $pattern = [])
    {
        $group = new RuleGroup($this, $this->group, $rule, null, $option, $pattern);

        foreach ($this->methodPrefix as $type => $val) {
            $item = $this->$type(':action', $val . ':action');
            $group->addRuleItem($item, $type);
        }

        return $group->prefix($route . '/');
    }

    /**
     * 注册视图路由
     * @access public
     * @param  string|array $rule 路由规则
     * @param  string       $template 路由模板地址
     * @param  array        $vars 模板变量
     * @param  array        $option 路由参数
     * @param  array        $pattern 变量规则
     * @return RuleItem
     */
    public function view($rule, $template = '', array $vars = [], array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $template, 'GET', $option, $pattern)->view($vars);
    }

    /**
     * 注册重定向路由
     * @access public
     * @param  string|array $rule 路由规则
     * @param  string       $route 路由地址
     * @param  array        $status 状态码
     * @param  array        $option 路由参数
     * @param  array        $pattern 变量规则
     * @return RuleItem
     */
    public function redirect($rule, $route = '', $status = 301, array $option = [], array $pattern = [])
    {
        return $this->rule($rule, $route, '*', $option, $pattern)->redirect()->status($status);
    }

    /**
     * 注册别名路由
     * @access public
     * @param  string  $rule 路由别名
     * @param  string  $route 路由地址
     * @param  array   $option 路由参数
     * @return AliasRule
     */
    public function alias($rule, $route, array $option = [])
    {
        $aliasRule = new AliasRule($this, $this->group, $rule, $route, $option);

        $this->alias[$rule] = $aliasRule;

        return $aliasRule;
    }

    /**
     * 获取别名路由定义
     * @access public
     * @param  string    $name 路由别名
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
     * @param  string|array  $method 请求类型
     * @param  string        $prefix 类型前缀
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
     * @param  string    $method 请求类型
     * @param  string    $prefix 类型前缀
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
     * @param  string        $name 方法名称
     * @param  array|bool    $resource 资源
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
     * @param  string        $name 方法名称
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
     * @param  string    $route 路由地址
     * @param  string    $method 请求类型
     * @param  array     $option 路由参数
     * @return RuleItem
     */
    public function miss($route, $method = '*', array $option = [])
    {
        return $this->group->addMissRule($route, $method, $option);
    }

    /**
     * 注册一个自动解析的URL路由
     * @access public
     * @param  string    $route 路由地址
     * @return RuleItem
     */
    public function auto($route)
    {
        return $this->group->addAutoRule($route);
    }

    /**
     * 检测URL路由
     * @access public
     * @param  string    $url URL地址
     * @param  string    $depr URL分隔符
     * @param  bool      $must 是否强制路由
     * @param  bool      $completeMatch   路由是否完全匹配
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
            // 检测跨域路由
            $result = $this->cross->check($this->request, $url, $depr, $completeMatch);
        }

        if (false !== $result) {
            // 路由匹配
            return $result;
        } elseif ($must) {
            // 强制路由不匹配则抛出异常
            throw new RouteNotFoundException();
        }

        // 默认路由解析
        return new UrlDispatch($url, ['depr' => $depr, 'auto_search' => $this->config->get('app.controller_auto_search')]);
    }

    /**
     * 检测域名的路由规则
     * @access protected
     * @return Domain
     */
    protected function checkDomain()
    {
        // 获取当前子域名
        $subDomain = $this->request->subDomain();

        $item = false;

        if ($subDomain && count($this->domains) > 1) {
            $domain  = explode('.', $subDomain);
            $domain2 = array_pop($domain);

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
                $this->request->panDomain($panDomain);
            }
        }

        if (false === $item) {
            // 检测当前完整域名
            $item = $this->domains[$this->host];
        }

        if (is_string($item)) {
            $item = $this->domains[$item];
        }

        return $item;
    }

    /**
     * 设置全局的路由分组参数
     * @access public
     * @param  string    $method     方法名
     * @param  array     $args       调用参数
     * @return RuleGroup
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->group, $method], $args);
    }
}
