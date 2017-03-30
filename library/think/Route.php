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
    private $rest = [
        'index'  => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'edit'   => ['get', '/:id/edit', 'edit'],
        'read'   => ['get', '/:id', 'read'],
        'save'   => ['post', '', 'save'],
        'update' => ['put', '/:id', 'update'],
        'delete' => ['delete', '/:id', 'delete'],
    ];

    // 不同请求类型的方法前缀
    private $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    // 路由绑定
    protected $bind;
    // 当前分组信息
    protected $group = [];
    protected $name  = [];
    // 当前域名
    protected $domain;
    // 域名对象
    protected $domains;
    // 全局路由变量
    protected $pattern = [];
    // 当前应用实例
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;

        // 默认分组
        $this->group = new RuleGroup($this, '');

        // 默认域名
        $host = $this->app['request']->host();

        // 注册默认分组到默认域名下
        $this->domain($host)->addRule($this->group);
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
        // 设置当前域名
        $this->setDomain($name);

        if (!isset($this->domains[$name])) {
            $this->domains[$name] = new Domain($this, $name, $option, $pattern);
        } else {
            $this->domains[$name]->option($option)->pattern($pattern);
        }

        // 执行域名路由
        if ($rule instanceof \Closure) {
            call_user_func($rule);
        }

        // 还原默认域名
        $this->setDomain($this->app['request']->host());

        // 返回域名对象
        return $this->domains[$name];
    }

    /**
     * 设置当前域名
     * @access public
     * @param string    $domain 域名
     * @return void
     */
    protected function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * 读取域名
     * @access public
     * @return array
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * 设置路由绑定
     * @access public
     * @param string     $bind 绑定信息
     * @return $this
     */
    public function bind($bind)
    {
        $this->bind = $bind;

        return $this;
    }

    /**
     * 读取路由绑定
     * @access public
     * @return string
     */
    public function getBind()
    {
        return $this->bind;
    }

    /**
     * 设置路由标识
     * @access public
     * @param string|array     $name 路由命名标识 数组表示批量设置
     * @param array            $value 路由地址及变量信息
     * @return array
     */
    public function name($name, $value = null)
    {
        if (is_array($name)) {
            $this->name = $name;
        } else {
            $this->name[strtolower($name)][] = $value;
        }

        return $this;
    }

    /**
     * 读取路由标识
     * @access public
     * @return string
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
     * 导入配置文件的路由规则
     * @access public
     * @param array     $rule 路由规则
     * @param string    $type 请求类型
     * @return void
     */
    public function import(array $rules, $type = '*')
    {
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
     * @param string    $route      路由地址
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
            $vars = $this->parseVar($rule);

            if (isset($option['ext'])) {
                $suffix = $option['ext'];
            } elseif ($this->group->getOption('ext')) {
                $suffix = $this->group->getOption('ext');
            } else {
                $suffix = null;
            }

            $this->name($name, [$rule, $vars, $this->domain, $suffix]);
        }

        // 创建路由规则实例
        $rule = new RuleItem($this, $this->group, $rule, $route, $method, $option, $pattern);

        // 添加到当前分组
        $this->group->addRule($rule, $method);

        return $rule;
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

        // 上级分组
        $parentGroup = $this->group;

        // 创建分组实例
        $group = new RuleGroup($this, $name, $option, $pattern);

        // 注册子分组
        $parentGroup->addRule($group);

        // 设置当前分组
        $this->group = $group;

        // 注册分组路由
        if ($route instanceof \Closure) {
            call_user_func($route);
        } else {
            $this->rules($route);
        }

        // 还原当前分组
        $this->group = $parentGroup;

        // 注册分组到当前域名
        $this->domains[$this->domain]->addRule($group);

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
     * @return void
     */
    public function resource($rule, $route = '', $option = [], $pattern = [])
    {
        $resource = new Resource($this, $rule, $route, $option, $pattern, $this->rest);

        // 注册分组到当前域名
        $this->domains[$this->domain]->addRule($resource);

        return $resource;
    }

    /**
     * 注册控制器路由 操作方法对应不同的请求后缀
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
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
     * @return void
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
     * @return string|array
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
     * @param string    $method 请求类型
     * @param string    $prefix 类型前缀
     * @return void
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
     * @return void
     */
    public function getMethodPrefix($method)
    {
        return isset($this->methodPrefix[strtolower($method)]) ? $this->methodPrefix[strtolower($method)] : null;
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
     * @param Request   $request Request请求对象
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $checkDomain 是否检测域名规则
     * @param bool      $must 是否强制路由
     * @return false|array
     */
    public function check($request, $url, $depr = '/', $checkDomain = false, $must = false)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace($depr, '|', $url);

        $method = strtolower($request->method());

        // 获取当前域名
        $host = $request->host();

        // 域名路由检测
        $domain = false;

        if ($checkDomain) {
            // 自动检测域名路由
            $domain = $this->checkDomain($host);
        }

        if (false === $domain) {
            // 完整域名或者IP配置
            $domain = $this->domains[$host];
        }

        $result = $domain->check($request, $url, $depr);

        if (false !== $result) {
            // 路由匹配
            return $result;
        } elseif ($must) {
            // 强制路由不匹配则抛出异常
            throw new RouteNotFoundException();
        } else {
            // 默认路由解析
            return new UrlDispatch($url, ['depr' => $depr, 'auto_search' => $this->app->config('app.controller_auto_search')]);
        }
    }

    /**
     * 检测域名的路由规则
     * @access public
     * @param string    $host 当前主机地址
     * @return Domain|false
     */
    protected function checkDomain($host)
    {
        $rootDomain = $this->app['config']->get('url_domain_root');
        $domains    = $this->domains;

        if ($rootDomain) {
            // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
            $domain = explode('.', rtrim(stristr($host, $rootDomain, true), '.'));
        } else {
            $domain = explode('.', $host, -2);
        }

        // 子域名配置
        $item = false;

        if (!empty($domain)) {
            // 当前子域名
            $subDomain = implode('.', $domain);
            $domain2   = array_pop($domain);

            if ($domain) {
                // 存在三级域名
                $domain3 = array_pop($domain);
            }

            if ($subDomain && isset($domains[$subDomain])) {
                // 子域名配置
                $item = $domains[$subDomain];
            } elseif (isset($domains['*.' . $domain2]) && !empty($domain3)) {
                // 泛三级域名
                $item      = $domains['*.' . $domain2];
                $panDomain = $domain3;
            } elseif (isset($domains['*']) && !empty($domain2)) {
                // 泛二级域名
                if ('www' != $domain2) {
                    $item      = $domains['*'];
                    $panDomain = $domain2;
                }
            }

            if (isset($panDomain)) {
                // 保存当前泛域名
                $request->route(['__domain__' => $panDomain]);
            }
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
                $name       = substr($val, 1);
                $var[$name] = $optional ? 2 : 1;
            }
        }

        return $var;
    }
}
