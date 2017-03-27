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

use think\exception\HttpException;
use think\route\Domain;
use think\route\RouteRule as Rule;
use think\route\RuleGroup;

class Route
{

    // 路由规则
    private $rules = [
        'get'     => [],
        'post'    => [],
        'put'     => [],
        'delete'  => [],
        'patch'   => [],
        'head'    => [],
        'options' => [],
        '*'       => [],
        'alias'   => [],
        'domain'  => [],
        'pattern' => [],
        'name'    => [],
    ];

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

    // 子域名
    private $subDomain = '';
    // 域名绑定
    private $bind = [];
    // 当前分组信息
    private $group = [];

    // 当前子域名绑定
    private $domainBind;
    private $domainRule;
    // 当前域名
    private $domain;
    // 当前路由执行过程中的参数
    private $option = [];

    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 注册变量规则
     * @access public
     * @param string|array  $name 变量名
     * @param string        $rule 变量规则
     * @return void
     */
    public function pattern($name = null, $rule = '')
    {
        if (is_array($name)) {
            $this->rules['pattern'] = array_merge($this->rules['pattern'], $name);
        } else {
            $this->rules['pattern'][$name] = $rule;
        }

        return $this;
    }

    /**
     * 注册子域名部署规则
     * @access public
     * @param string|array  $name 子域名
     * @param mixed         $rule 路由规则
     * @param array         $option 路由参数
     * @param array         $pattern 变量规则
     * @return void
     */
    public function domain($name, $rule = '', $option = [], $pattern = [])
    {

        // 执行闭包
        $this->setDomain($name);
        $domain               = new Domain($this, $name, $option, $pattern);
        $this->domains[$name] = $domain;
        call_user_func_array($rule, []);
        $this->setDomain(null);

        return $domain;
    }

    private function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * 设置路由绑定
     * @access public
     * @param mixed     $bind 绑定信息
     * @param string    $type 绑定类型 默认为module 支持 namespace class controller
     * @return mixed
     */
    public function bind($bind, $type = 'module')
    {
        $this->bind = ['type' => $type, $type => $bind];

        return $this;
    }

    /**
     * 设置或者获取路由标识
     * @access public
     * @param string|array     $name 路由命名标识 数组表示批量设置
     * @param array            $value 路由地址及变量信息
     * @return array
     */
    public function name($name = '', $value = null)
    {
        if (is_array($name)) {
            $this->rules['name'] = $name;

            return $this;
        } elseif ('' === $name) {
            return $this->rules['name'];
        } elseif (!is_null($value)) {
            $this->rules['name'][strtolower($name)][] = $value;

            return $this;
        } else {
            $name = strtolower($name);

            return isset($this->rules['name'][$name]) ? $this->rules['name'][$name] : null;
        }
    }

    /**
     * 读取路由绑定
     * @access public
     * @param string    $type 绑定类型
     * @return mixed
     */
    public function getBind($type)
    {
        return isset($this->bind[$type]) ? $this->bind[$type] : null;
    }

    /**
     * 导入配置文件的路由规则
     * @access public
     * @param array     $rule 路由规则
     * @param string    $type 请求类型
     * @return void
     */
    public function import(array $rule, $type = '*')
    {
        // 检查域名部署
        if (isset($rule['__domain__'])) {
            $this->domain($rule['__domain__']);
            unset($rule['__domain__']);
        }

        // 检查变量规则
        if (isset($rule['__pattern__'])) {
            $this->pattern($rule['__pattern__']);
            unset($rule['__pattern__']);
        }

        // 检查路由别名
        if (isset($rule['__alias__'])) {
            $this->alias($rule['__alias__']);
            unset($rule['__alias__']);
        }

        // 检查资源路由
        if (isset($rule['__rest__'])) {
            $this->resource($rule['__rest__']);
            unset($rule['__rest__']);
        }

        $this->registerRules($rule, strtolower($type));

        return $this;
    }

    // 批量注册路由
    protected function registerRules($rules, $type = '*')
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
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $type 请求类型
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public function rule($rule, $route, $type = '*', $option = [], $pattern = [])
    {
        if (is_array($rule)) {
            list($name, $rule) = $rule;
        } elseif (is_string($route)) {
            $name = $route;
        }
        if (isset($name)) {
            $vars = $this->parseVar($rule);
            $this->setName($name, $rule, $vars, $option);
        }
        $type = strtolower($type);

        $rule = new Rule($this, $rule, $route, $type, $option, $pattern);

        $groupName = $this->getCurrentGroup();
        $group     = $this->getRuleGroup($groupName);

        $group->addRule($rule, $type);

        return $rule;
    }

    public function setName($name, $vars = [], $option = [])
    {
        $group = $this->getCurrentGroup();
        $key   = $group ? $group() . ($rule ? '/' . $rule : '') : $rule;

        $suffix = isset($option['ext']) ? $option['ext'] : null;

        $this->name[strtolower($name)] = [$key, $vars, $this->domain, $suffix];
    }

    /**
     * 注册路由分组
     * @access public
     * @param string|array  $name       分组名称或者参数
     * @param \Closure      $closure    分组路由
     * @param array         $option     路由参数
     * @param array         $pattern    变量规则
     * @return RuleGroup
     */
    public function group($name, $closure, $option = [], $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }

        if ($this->group) {
            // 存在上级分组
            $parentGroupName = $this->getCurrentGroup();
        }

        // 分组开始
        $this->startGroup($name);

        $groupName = $this->getCurrentGroup();
        $group     = new RuleGroup($this, $groupName, $option, $pattern);

        $this->setRuleGroup($groupName, $group);

        if (isset($parentGroupName)) {
            $parentGroup = $this->getRuleGroup($parentGroupName);
            $parentGroup->addRule($group);
        }

        // 注册分组路由
        call_user_func_array($closure);

        // 结束当前分组
        $this->endGroup();

        if ($this->domain) {
            $this->domains[$this->domain]->addRule($group);
        }
        return $group;
    }

    public function getRuleGroup($name)
    {
        if (!isset($this->rules[$name])) {
            $this->rules[$name] = new RuleGroup($this, $name);

            if ($this->domain) {
                // 给域名添加分组规则
                $this->domains[$this->domain]->addRule($this->rules[$name]);
            }
        }
        return $this->rules[$name];
    }

    public function setRuleGroup($name, $group)
    {
        $this->rules[$name] = $group;
        return $this;
    }

    public function startGroup($name)
    {
        $this->group[] = $name;
    }

    public function endGroup()
    {
        array_pop($this->group);
    }

    public function getCurrentGroup()
    {
        return implode('/', $this->group) ?: '__default__';
    }

    /**
     * 注册路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
        if (is_array($rule)) {
            foreach ($rule as $key => $val) {
                if (is_array($val)) {
                    list($val, $option, $pattern) = array_pad($val, 3, []);
                }

                $this->resource($key, $val, $option, $pattern);
            }
        } else {
            if (strpos($rule, '.')) {
                // 注册嵌套资源路由
                $array = explode('.', $rule);
                $last  = array_pop($array);
                $item  = [];

                foreach ($array as $val) {
                    $item[] = $val . '/:' . (isset($option['var'][$val]) ? $option['var'][$val] : $val . '_id');
                }

                $rule = implode('/', $item) . '/' . $last;
            }
            // 注册资源路由
            foreach ($this->rest as $key => $val) {
                if ((isset($option['only']) && !in_array($key, $option['only']))
                    || (isset($option['except']) && in_array($key, $option['except']))) {
                    continue;
                }

                if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);
                } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
                }

                $item           = ltrim($rule . $val[1], '/');
                $option['rest'] = $key;

                $this->rule($item . '$', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }

        return $this;
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
     * rest方法定义和修改
     * @access public
     * @param string        $name 方法名称
     * @param array|bool    $resource 资源
     * @return void
     */
    public function rest($name, $resource = [])
    {
        if (is_array($name)) {
            $this->rest = $resource ? $name : array_merge($this->rest, $name);
        } else {
            $this->rest[$name] = $resource;
        }
    }

    /**
     * 注册未匹配路由规则后的处理
     * @access public
     * @param string    $route 路由地址
     * @param string    $method 请求类型
     * @param array     $option 路由参数
     * @return void
     */
    public function miss($route, $method = '*', $option = [])
    {
        $this->rule('__miss__', $route, $method, $option, []);
    }

    /**
     * 注册一个自动解析的URL路由
     * @access public
     * @param string    $route 路由地址
     * @return void
     */
    public function auto($route)
    {
        $this->rule('__auto__', $route, '*', [], []);
    }

    /**
     * 获取或者批量设置路由定义
     * @access public
     * @param mixed $rules 请求类型或者路由定义数组
     * @return array
     */
    public function rules($rules = '')
    {
        if (is_array($rules)) {
            $this->rules = $rules;
        } elseif ($rules) {
            return true === $rules ? $this->rules : $this->rules[strtolower($rules)];
        } else {
            $rules = $this->rules;
            unset($rules['pattern'], $rules['alias'], $rules['domain'], $rules['name']);
            return $rules;
        }
    }

    /**
     * 检测子域名部署
     * @access public
     * @param Request   $request Request请求对象
     * @param array     $rules 当前路由规则
     * @param string    $method 请求类型
     * @return void
     */
    public function checkDomain($request, &$rules, $method = 'get')
    {
        // 开启子域名部署 支持二级和三级域名
        if (!empty($this->domains)) {
            $host = $request->host();
            if (isset($this->domains[$host])) {
                // 完整域名或者IP配置
                $item = $this->domains[$host];
            } else {
                // 自动检测当前域名
                $item = $this->matchDomain($this->domains);
            }

            if (!empty($item)) {
                return $item->check($request, $url);
            }
        }
        return false;
    }

    protected function matchDomain($domains)
    {
        $rootDomain = $this->app['config']->get('url_domain_root');
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
            $subDomain       = implode('.', $domain);
            $this->subDomain = $subDomain;
            $domain2         = array_pop($domain);
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
     * 检测URL路由
     * @access public
     * @param Request   $request Request请求对象
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $checkDomain 是否检测域名规则
     * @return false|array
     */
    public function check($request, $url, $depr = '/', $checkDomain = false)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace($depr, '|', $url);

        // 检测别名路由
        if (isset($this->alias[$url]) || isset($this->alias[strstr($url, '|', true)])) {
            // 检测路由别名
            $result = $this->checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }

        $method = strtolower($request->method());

        // 检测域名部署
        if ($checkDomain) {
            $result = $this->checkDomain($url, $request, $method);
            if (false !== $result) {
                return $result;
            }
        }

        // 检测URL绑定
        $return = $this->checkUrlBind($url, $rules, $depr);

        if (false !== $return) {
            return $return;
        }

        if ('|' != $url) {
            $url = rtrim($url, '|');
        }
        $url = str_replace('|', '/', $url);

        return $this->checkRoute($this->rules, $url, $request);
    }

    /**
     * 检测路由规则
     * @access private
     * @param Request   $request
     * @param array     $rules 路由规则
     * @param string    $url URL地址
     * @param string    $depr URL分割符
     * @param string    $group 路由分组名
     * @param array     $options 路由参数（分组）
     * @return mixed
     */
    private function checkRoute($rules, $url, $depr = '/')
    {
        foreach ($rules as $key => $item) {
            $result = $item->check($url, $depr);

            if (false !== $result) {
                return $result;
            }
        }

        return false;
    }

    /**
     * 检测路由别名
     * @access private
     * @param Request   $request
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('|', $url);
        $alias = array_shift($array);
        $item  = $this->alias[$alias];

        if (is_array($item)) {
            list($rule, $option) = $item;
            $action              = $array[0];

            if (isset($option['allow']) && !in_array($action, explode(',', $option['allow']))) {
                // 允许操作
                return false;
            } elseif (isset($option['except']) && in_array($action, explode(',', $option['except']))) {
                // 排除操作
                return false;
            }

            if (isset($option['method'][$action])) {
                $option['method'] = $option['method'][$action];
            }
        } else {
            $rule = $item;
        }

        $bind = implode('|', $array);

        // 参数有效性检查
        if (isset($option) && !$this->checkOption($option, $request)) {
            // 路由不匹配
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // 路由到类
            return $this->bindToClass($bind, substr($rule, 1), $depr);
        } elseif (0 === strpos($rule, '@')) {
            // 路由到控制器类
            return $this->bindToController($bind, substr($rule, 1), $depr);
        } else {
            // 路由到模块/控制器
            return $this->bindToModule($bind, $rule, $depr);
        }
    }

    /**
     * 检测URL绑定
     * @access private
     * @param string    $url URL地址
     * @param array     $rules 路由规则
     * @param string    $depr URL分隔符
     * @return mixed
     */
    private function checkUrlBind(&$url, &$rules, $depr = '/')
    {
        if (!empty($this->bind)) {
            $type = $this->bind['type'];
            $bind = $this->bind[$type];

            // 记录绑定信息
            $this->app->log('[ BIND ] ' . var_export($bind, true));

            // 如果有URL绑定 则进行绑定检测
            switch ($type) {
                case 'class':
                    // 绑定到类
                    return $this->bindToClass($url, $bind, $depr);
                case 'controller':
                    // 绑定到控制器类
                    return $this->bindToController($url, $bind, $depr);
                case 'namespace':
                    // 绑定到命名空间
                    return $this->bindToNamespace($url, $bind, $depr);
            }
        }

        return false;
    }

    /**
     * 绑定到类
     * @access public
     * @param string    $url URL地址
     * @param string    $class 类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToClass($url, $class, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new CallbackDispatch([$class, $action]);
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param string    $url URL地址
     * @param string    $namespace 命名空间
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToNamespace($url, $namespace, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_controller');
        $method = !empty($array[1]) ? $array[1] : $this->app['config']->get('default_action');

        if (!empty($array[2])) {
            $this->parseUrlParams($array[2]);
        }

        return new CallbackDispatch([$namespace . '\\' . Loader::parseName($class, 1), $method]);
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器名 （支持带模块名 index/user ）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToController($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ControllerDispatch($controller . '/' . $action);
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public function bindToModule($url, $controller, $depr = '/')
    {
        $url    = str_replace($depr, '|', $url);
        $array  = explode('|', $url, 2);
        $action = !empty($array[0]) ? $array[0] : $this->app['config']->get('default_action');

        if (!empty($array[1])) {
            $this->parseUrlParams($array[1]);
        }

        return new ModuleDispatch($controller . '/' . $action);
    }

    /**
     * 解析模块的URL地址 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access public
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $autoSearch 是否自动深度搜索控制器
     * @return array
     */
    public function parseUrl($url, $depr = '/', $autoSearch = false)
    {

        if (isset($this->bind['module'])) {
            $bind = str_replace('/', $depr, $this->bind['module']);
            // 如果有模块/控制器绑定
            $url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
        }

        $url              = str_replace($depr, '|', $url);
        list($path, $var) = $this->parseUrlPath($url);
        $route            = [null, null, null];

        if (isset($path)) {
            // 解析模块
            $module = $this->app->config('app_multi_module') ? array_shift($path) : null;
            if ($autoSearch) {
                // 自动搜索控制器
                $dir    = $this->app->getAppPath() . ($module ? $module . DIRECTORY_SEPARATOR : '') . $this->app->config('url_controller_layer');
                $suffix = $this->app->getSuffix() || $this->app->config('controller_suffix') ? ucfirst($this->app->config('url_controller_layer')) : '';
                $item   = [];
                $find   = false;

                foreach ($path as $val) {
                    $item[] = $val;
                    $file   = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $val) . $suffix . '.php';
                    $file   = pathinfo($file, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . Loader::parseName(pathinfo($file, PATHINFO_FILENAME), 1) . '.php';
                    if (is_file($file)) {
                        $find = true;
                        break;
                    } else {
                        $dir .= DIRECTORY_SEPARATOR . Loader::parseName($val);
                    }
                }

                if ($find) {
                    $controller = implode('.', $item);
                    $path       = array_slice($path, count($item));
                } else {
                    $controller = array_shift($path);
                }
            } else {
                // 解析控制器
                $controller = !empty($path) ? array_shift($path) : null;
            }

            // 解析操作
            $action = !empty($path) ? array_shift($path) : null;

            // 解析额外参数
            $this->parseUrlParams(empty($path) ? '' : implode('|', $path));

            // 封装路由
            $route = [$module, $controller, $action];

            // 检查地址是否被定义过路由
            $name = strtolower($module . '/' . Loader::parseName($controller, 1) . '/' . $action);

            $name2 = '';

            if (empty($module) || isset($bind) && $module == $bind) {
                $name2 = strtolower(Loader::parseName($controller, 1) . '/' . $action);
            }

            if (isset($this->rules['name'][$name]) || isset($this->rules['name'][$name2])) {
                throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
            }
        }

        return new ModuleDispatch($route);
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access private
     * @param string    $url URL地址
     * @return array
     */
    private function parseUrlPath($url)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');
        $var = [];

        if (false !== strpos($url, '?')) {
            // [模块/控制器/操作?]参数1=值1&参数2=值2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [模块/控制器/操作]
            $path = explode('/', $url);
        } elseif (false !== strpos($url, '=')) {
            // 参数1=值1&参数2=值2...
            parse_str($url, $var);
        } else {
            $path = [$url];
        }

        return [$path, $var];
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access private
     * @param string    $rule 路由规则
     * @param array     $var 变量
     * @return void
     */
    private function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if ($this->app['config']->get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }

        // 设置当前请求的参数
        $this->app['request']->route($var);
    }

    // 分析路由规则中的变量
    private function parseVar($rule)
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
