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
use think\Hook;
use think\Log;
use think\Request;
use think\Response;

class Route
{
    // 路由规则
    private static $rules = [
        'GET'     => [],
        'POST'    => [],
        'PUT'     => [],
        'DELETE'  => [],
        'HEAD'    => [],
        'OPTIONS' => [],
        '*'       => [],
    ];

    // REST路由操作方法定义
    private static $rest = [
        'index'  => ['GET', '', 'index'],
        'create' => ['GET', '/create', 'create'],
        'edit'   => ['GET', '/:id/edit', 'edit'],
        'read'   => ['GET', '/:id', 'read'],
        'save'   => ['POST', '', 'save'],
        'update' => ['PUT', '/:id', 'update'],
        'delete' => ['DELETE', '/:id', 'delete'],
    ];

    // 不同请求类型的方法前缀
    private static $methodPrefix = [
        'GET'    => 'get',
        'POST'   => 'post',
        'PUT'    => 'put',
        'DELETE' => 'delete',
    ];

    // URL映射规则
    private static $map = [];
    // 子域名部署规则
    private static $domain = [];
    // 子域名
    private static $subDomain = '';
    // 变量规则
    private static $pattern = [];
    // 域名绑定
    private static $bind = [];
    // 当前分组
    private static $group;
    // 当前参数
    private static $option = [];
    // 路由别名
    private static $alias = [];

    /**
     * 注册或者获取URL映射规则
     * @access public
     * @param string|array $map 映射名
     * @param string $route 路由地址
     * @return mixed
     */
    public static function map($map = '', $route = '')
    {
        return self::setting('map', $map, $route);
    }

    /**
     * 注册或者获取变量规则
     * @access public
     * @param string|array $name 变量名
     * @param string $rule 变量规则
     * @return mixed
     */
    public static function pattern($name = '', $rule = '')
    {
        return self::setting('pattern', $name, $rule);
    }

    /**
     * 注册或者获取子域名部署规则
     * @access public
     * @param string|array $domain 子域名
     * @param mixed $rule 路由规则
     * @return mixed
     */
    public static function domain($domain = '', $rule = '')
    {
        return self::setting('domain', $domain, $rule);
    }

    /**
     * 设置属性
     * @access public
     * @param string $var 属性名称
     * @param string|array $name 变量名称
     * @param mixed $value 变量值
     * @return mixed
     */
    private static function setting($var, $name = '', $value = '')
    {
        if (is_array($name)) {
            self::${$var} = self::${$var}+$name;
        } elseif (empty($value)) {
            return empty($name) ? self::${$var} : self::${$var}[$name];
        } else {
            self::${$var}[$name] = $value;
        }
    }

    /**
     * 设置和读取路由绑定
     * @access public
     * @param string $type 请求类型
     * @param mixed $bind 绑定信息
     * @return mixed
     */
    public static function bind($type, $bind = '')
    {
        if ('' == $bind) {
            return isset(self::$bind[$type]) ? self::$bind[$type] : null;
        } else {
            self::$bind = ['type' => $type, $type => $bind];
        }
    }

    /**
     * 导入配置文件的路由规则
     * @access public
     * @param array $rule 路由规则
     * @param string $type 请求类型
     * @return void
     */
    public static function import(array $rule, $type = '*')
    {
        // 检查域名部署
        if (isset($rule['__domain__'])) {
            self::domain($rule['__domain__']);
            unset($rule['__domain__']);
        }

        // 检查变量规则
        if (isset($rule['__pattern__'])) {
            self::pattern($rule['__pattern__']);
            unset($rule['__pattern__']);
        }

        // 检查路由映射
        if (isset($rule['__map__'])) {
            self::map($rule['__map__']);
            unset($rule['__map__']);
        }

        // 检查路由别名
        if (isset($rule['__alias__'])) {
            self::alias($rule['__alias__']);
            unset($rule['__alias__']);
        }

        // 检查资源路由
        if (isset($rule['__rest__'])) {
            self::resource($rule['__rest__']);
            unset($rule['__rest__']);
        }

        $type = strtoupper($type);
        foreach ($rule as $key => $val) {
            if (is_numeric($key)) {
                $key = array_shift($val);
            }
            if (empty($val)) {
                continue;
            }
            if (0 === strpos($key, '[')) {
                $key    = substr($key, 1, -1);
                $result = ['routes' => $val, 'option' => [], 'pattern' => []];
            } elseif (is_array($val)) {
                $result = ['route' => $val[0], 'option' => $val[1], 'pattern' => isset($val[2]) ? $val[2] : []];
            } else {
                $result = ['route' => $val, 'option' => [], 'pattern' => []];
            }
            self::$rules[$type][$key] = $result;
        }
    }

    /**
     * 注册路由规则
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param string $type 请求类型
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function rule($rule, $route = '', $type = '*', $option = [], $pattern = [], $group = '')
    {
        $group  = $group ?: self::$group;
        $option = $option ?: self::$option;

        $type = strtoupper($type);
        if (strpos($type, '|')) {
            foreach (explode('|', $type) as $val) {
                self::rule($rule, $route, $val, $option, $pattern, $group);
            }
        } else {
            if (is_array($rule)) {
                foreach ($rule as $key => $val) {
                    if (is_numeric($key)) {
                        $key = array_shift($val);
                    }
                    if (is_array($val)) {
                        $result = ['route' => $val[0], 'option' => $val[1], 'pattern' => isset($val[2]) ? $val[2] : []];
                    } else {
                        $result = ['route' => $val, 'option' => $option, 'pattern' => $pattern];
                    }
                    if ($group) {
                        self::$rules[$type][$group]['routes'][$key] = [$result['route'], $result['option'], $result['pattern']];
                    } else {
                        self::$rules[$type][$key] = $result;
                    }
                }
            } else {
                if ($group) {
                    self::$rules[$type][$group]['routes'][$rule] = [$route, $option, $pattern];
                } else {
                    self::$rules[$type][$rule] = ['route' => $route, 'option' => $option, 'pattern' => $pattern];
                }
            }
        }
    }

    /**
     * 设置当前的路由分组
     * @access public
     * @param array $option 路由参数
     * @return void
     */
    public static function setGroup($name)
    {
        self::$group = $name;
    }

    /**
     * 设置当前的路由参数
     * @access public
     * @param array $option 路由参数
     * @return void
     */
    public static function setOption($option)
    {
        self::$option = $option;
    }

    /**
     * 注册路由分组
     * @access public
     * @param string|array $name 分组名称或者参数
     * @param array|\Closure $routes 路由地址
     * @param array $option 路由参数
     * @param string $type 请求类型
     * @param array $pattern 变量规则
     * @return void
     */
    public static function group($name, $routes, $option = [], $type = '*', $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }
        $type = strtoupper($type);
        if (!empty($name)) {
            if ($routes instanceof \Closure) {
                self::setGroup($name);
                call_user_func_array($routes, []);
                self::setGroup(null);
                self::$rules[$type][$name]['option']  = $option;
                self::$rules[$type][$name]['pattern'] = $pattern;
            } else {
                self::$rules[$type][$name] = ['routes' => $routes, 'option' => $option, 'pattern' => $pattern];
            }
        } else {
            if ($routes instanceof \Closure) {
                // 闭包注册
                self::setOption($option);
                call_user_func_array($routes, []);
                self::setOption([]);
            } else {
                // 批量注册路由
                self::rule($routes, '', $type, $option, $pattern);
            }
        }
    }

    /**
     * 注册路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function any($rule, $route = '', $option = [], $pattern = [], $group = '')
    {
        self::rule($rule, $route, '*', $option, $pattern, $group);
    }

    /**
     * 注册GET路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function get($rule, $route = '', $option = [], $pattern = [], $group = '')
    {
        self::rule($rule, $route, 'GET', $option, $pattern, $group);
    }

    /**
     * 注册POST路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function post($rule, $route = '', $option = [], $pattern = [], $group = '')
    {
        self::rule($rule, $route, 'POST', $option, $pattern, $group);
    }

    /**
     * 注册PUT路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function put($rule, $route = '', $option = [], $pattern = [], $group = '')
    {
        self::rule($rule, $route, 'PUT', $option, $pattern, $group);
    }

    /**
     * 注册DELETE路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @param string $group 所属分组
     * @return void
     */
    public static function delete($rule, $route = '', $option = [], $pattern = [], $group = '')
    {
        self::rule($rule, $route, 'DELETE', $option, $pattern, $group);
    }

    /**
     * 注册资源路由
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @return void
     */
    public static function resource($rule, $route = '', $option = [], $pattern = [])
    {
        if (is_array($rule)) {
            foreach ($rule as $key => $val) {
                if (is_array($val)) {
                    list($val, $option, $pattern) = array_pad($val, 3, []);
                }
                self::resource($key, $val, $option, $pattern);
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
            foreach (self::$rest as $key => $val) {
                if ((isset($option['only']) && !in_array($key, $option['only']))
                    || (isset($option['except']) && in_array($key, $option['except']))) {
                    continue;
                }
                if (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
                }
                $item = ltrim($rule . $val[1], '/');
                self::rule($item ? $item . '$' : '', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }
    }

    /**
     * 注册控制器路由 操作方法对应不同的请求后缀
     * @access public
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @param array $pattern 变量规则
     * @return void
     */
    public static function controller($rule, $route = '', $option = [], $pattern = [])
    {
        foreach (self::$methodPrefix as $type => $val) {
            self::$type($rule . '/:action', $route . '/' . $val . ':action', $option, $pattern);
        }
    }

    /**
     * 注册别名路由
     * @access public
     * @param string|array $rule 路由别名
     * @param string $route 路由地址
     * @param array $option 路由参数
     * @return void
     */
    public static function alias($rule = null, $route = '', $option = [])
    {
        if (is_null($rule)) {
            return self::$alias;
        } elseif (is_array($rule)) {
            self::$alias = array_merge(self::$alias, $rule);
        } else {
            self::$alias[$rule] = $option ? [$route, $option] : $route;
        }
    }

    /**
     * 设置不同请求类型下面的方法前缀
     * @access public
     * @param string $method 请求类型
     * @param string $prefix 类型前缀
     * @return void
     */
    public static function setMethodPrefix($method, $prefix = '')
    {
        if (is_array($method)) {
            self::$methodPrefix = array_merge(self::$methodPrefix, array_change_key_case($method, CASE_UPPER));
        } else {
            self::$methodPrefix[strtoupper($method)] = $prefix;
        }
    }

    /**
     * rest方法定义和修改
     * @access public
     * @param string $name 方法名称
     * @param array $resourece 资源
     * @return void
     */
    public static function rest($name, $resource = [])
    {
        if (is_array($name)) {
            self::$rest = array_merge(self::$rest, $name);
        } else {
            self::$rest[$name] = $resource;
        }
    }

    /**
     * 注册未匹配路由规则后的处理
     * @access public
     * @param string $route 路由地址
     * @param string $method 请求类型
     * @param array $option 路由参数
     * @return void
     */
    public static function miss($route, $method = '*', $option = [])
    {
        self::rule('__miss__', $route, $method, $option, []);
    }

    /**
     * 获取路由定义
     * @access public
     * @param string $method 请求类型
     * @return array
     */
    public static function getRules($method = '')
    {
        if ($method) {
            return self::$rules[$method];
        } else {
            return self::$rules['*'] + self::$rules['GET'] + self::$rules['POST'] + self::$rules['PUT'] + self::$rules['DELETE'];
        }
    }

    /**
     * 检测子域名部署
     * @access public
     * @return void
     */
    public static function checkDomain()
    {
        // 域名规则
        $rules = self::$domain;
        // 开启子域名部署 支持二级和三级域名
        if (!empty($rules)) {
            if (isset($rules[$_SERVER['HTTP_HOST']])) {
                // 完整域名或者IP配置
                $rule = $rules[$_SERVER['HTTP_HOST']];
            } else {
                $rootDomain = Config::get('url_domain_root');
                if ($rootDomain) {
                    // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
                    $domain = explode('.', rtrim(stristr($_SERVER['HTTP_HOST'], $rootDomain, true), '.'));
                } else {
                    $domain = explode('.', $_SERVER['HTTP_HOST'], -2);
                }
                // 子域名配置
                if (!empty($domain)) {
                    // 当前子域名
                    $subDomain       = implode('.', $domain);
                    self::$subDomain = $subDomain;
                    $domain2         = array_pop($domain);
                    if ($domain) {
                        // 存在三级域名
                        $domain3 = array_pop($domain);
                    }
                    if ($subDomain && isset($rules[$subDomain])) {
                        // 子域名配置
                        $rule = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        // 泛三级域名
                        $rule      = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2)) {
                        // 泛二级域名
                        if ('www' != $domain2) {
                            $rule      = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
            }
            if (!empty($rule)) {
                // 子域名部署规则
                if ($rule instanceof \Closure) {
                    // 执行闭包
                    $reflect    = new \ReflectionFunction($rule);
                    self::$bind = $reflect->invokeArgs([]);
                    return;
                }

                if (strpos($rule, '?')) {
                    // 传入其它参数
                    $array  = parse_url($rule);
                    $result = $array['path'];
                    parse_str($array['query'], $params);
                    if (isset($panDomain)) {
                        $pos = array_search('*', $params);
                        if (false !== $pos) {
                            // 泛域名作为参数
                            $params[$pos] = $panDomain;
                        }
                    }
                    $_GET = array_merge($_GET, $params);
                } else {
                    $result = $rule;
                }

                if (0 === strpos($result, '\\')) {
                    // 绑定到命名空间 例如 \app\index\behavior
                    self::$bind = ['type' => 'namespace', 'namespace' => $result];
                } elseif (0 === strpos($result, '@')) {
                    // 绑定到类 例如 \app\index\controller\User
                    self::$bind = ['type' => 'class', 'class' => substr($result, 1)];
                } elseif (0 === strpos($result, '[')) {
                    // 绑定到分组 例如 [user]
                    self::$bind = ['type' => 'group', 'group' => substr($result, 1, -1)];
                } else {
                    // 绑定到模块/控制器 例如 index/user
                    self::$bind = ['type' => 'module', 'module' => $result];
                }
            }
        }
    }

    /**
     * 检测URL路由
     * @access public
     * @param \think\Request $request Request请求对象
     * @param string $url URL地址
     * @param string $depr URL分隔符
     * @param bool $checkDomain 是否检测域名规则
     * @return false|array
     */
    public static function check($request, $url, $depr = '/', $checkDomain = false)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        if ('/' != $depr) {
            $url = str_replace($depr, '/', $url);
        }

        if (isset(self::$map[$url])) {
            // URL映射（完整静态URL匹配）
            return self::parseUrl(self::$map[$url], $depr);
        }

        if (strpos($url, '/') && isset(self::$alias[strstr($url, '/', true)])) {
            // 路由别名
            $array = explode('/', $url, 2);
            $item  = self::$alias[$array[0]];

            if (is_array($item)) {
                list($rule, $option) = $item;
            } else {
                $rule = $item;
            }
            // 参数有效性检查
            if (isset($option) && !self::checkOption($option, $url, $request)) {
                // 路由不匹配
            } elseif (0 === strpos($rule, '\\')) {
                // 路由到类
                return self::bindToClass($array[1], substr($rule, 1));
            } elseif (0 === strpos($url, '@')) {
                // 路由到控制器类
                return self::bindToController($array[1], substr($rule, 1));
            } else {
                // 路由到模块/控制器
                return self::bindToModule($array[1], $rule);
            }
        }

        // 获取当前请求类型的路由规则
        $rules = self::$rules[$request->method()];

        if (!empty(self::$rules['*'])) {
            // 合并任意请求的路由规则
            $rules = array_merge(self::$rules['*'], $rules);
        }

        // 检测域名部署
        if ($checkDomain) {
            self::checkDomain($request);
        }
        // 检测URL绑定
        $return = self::checkUrlBind($url, $rules);
        if ($return) {
            return $return;
        }

        // 路由规则检测
        if (!empty($rules)) {
            if (isset($rules['__miss__'])) {
                // 指定未匹配路由的处理
                $miss = $rules['__miss__'];
                unset($rules['__miss__']);
            }
            foreach ($rules as $rule => $val) {
                $option  = isset($val['option']) ? $val['option'] : [];
                $pattern = isset($val['pattern']) ? $val['pattern'] : [];

                // 参数有效性检查
                if (!self::checkOption($option, $url, $request)) {
                    continue;
                }

                if (!empty($val['routes'])) {
                    // 分组路由
                    if ($pos = strpos($rule, ':') || $pos = strpos($rule, '<')) {
                        $str = substr($rule, 0, $pos);
                    } else {
                        $str = $rule;
                    }
                    if (0 !== strpos($url, $str)) {
                        continue;
                    }
                    // 匹配到路由分组
                    foreach ($val['routes'] as $key => $route) {
                        if (is_numeric($key)) {
                            $key = array_shift($route);
                        }

                        $key = $rule . ($key ? '/' . ltrim($key, '/') : '');
                        // 检查规则路由
                        if (is_array($route)) {
                            $option1 = $route[1];
                            // 检查参数有效性
                            if (!self::checkOption($option1, $url, $request)) {
                                continue;
                            }
                            $pattern = array_merge($pattern, isset($route[2]) ? $route[2] : []);
                            $route   = $route[0];
                            $option  = array_merge($option, $option1);
                        }
                        $result = self::checkRule($key, $route, $url, $pattern, $option);
                        if (false !== $result) {
                            $request->route(['rule' => $key, 'route' => $route, 'pattern' => $pattern, 'option' => $option]);
                            return $result;
                        }
                    }
                } else {
                    if (is_numeric($rule)) {
                        $rule = array_shift($val);
                    }
                    // 单项路由
                    $route = !empty($val['route']) ? $val['route'] : '';
                    // 规则路由
                    $result = self::checkRule($rule, $route, $url, $pattern, $option);
                    if (false !== $result) {
                        $request->route(['rule' => $rule, 'route' => $route, 'pattern' => $pattern, 'option' => $option]);
                        return $result;
                    }
                }
            }
            if (isset($miss)) {
                // 未匹配所有路由的路由规则处理
                if ($miss instanceof \Closure) {
                    // 执行闭包
                    return ['type' => 'function', 'function' => $miss, 'params' => []];
                }
                if (self::checkOption($miss['option'], $url, $request)) {
                    return self::parseRule('', $miss['route'], $url, []);
                }
            }
        }
        return false;
    }

    /**
     * 检测URL绑定
     * @access private
     * @param string $url URL地址
     * @param array $rules 路由规则
     * @return false
     */
    private static function checkUrlBind(&$url, &$rules)
    {
        if (!empty(self::$bind['type'])) {
            // 记录绑定信息
            APP_DEBUG && Log::record('[ BIND ] ' . var_export(self::$bind, true), 'info');
            // 如果有URL绑定 则进行绑定检测
            switch (self::$bind['type']) {
                case 'class':
                    // 绑定到类
                    return self::bindToClass($url, self::$bind['class']);
                case 'namespace':
                    // 绑定到命名空间
                    return self::bindToNamespace($url, self::$bind['namespace']);
                case 'module':
                    // 如果有模块/控制器绑定 针对路由到 模块/控制器 有效
                    $url = self::$bind['module'] . '/' . $url;
                    break;
                case 'group':
                    // 绑定到路由分组
                    $key = self::$bind['group'];
                    if (array_key_exists($key, $rules)) {
                        $rules = [$key => $rules[self::$bind['group']]];
                    }
            }
        }
        return false;
    }

    /**
     * 绑定到类
     * @access public
     * @param string $url URL地址
     * @param string $class 类名（带命名空间）
     * @return array
     */
    public static function bindToClass($url, $class)
    {
        $array = explode('/', $url, 2);
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'method', 'method' => [$class, $array[0] ?: Config::get('default_action')], 'params' => []];
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param string $url URL地址
     * @param string $namespace 命名空间
     * @return array
     */
    public static function bindToNamespace($url, $namespace)
    {
        $array  = explode('/', $url, 3);
        $class  = !empty($array[0]) ? $array[0] : Config::get('default_controller');
        $method = !empty($array[1]) ? $array[1] : Config::get('default_action');
        if (!empty($array[2])) {
            self::parseUrlParams($array[2]);
        }
        return ['type' => 'method', 'method' => [$namespace . '\\' . $class, $method], 'params' => []];
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param string $url URL地址
     * @param string $module 模块名
     * @return array
     */
    public static function bindToController($url, $controller)
    {
        $array  = explode('/', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'method', 'method' => [$controller, $action], 'params' => []];
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param string $url URL地址
     * @param string $class 控制器类名（带命名空间）
     * @return array
     */
    public static function bindToModule($url, $controller)
    {
        $array  = explode('/', $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'module', 'module' => $controller . '/' . $action, 'params' => []];
    }

    /**
     * 路由参数有效性检查
     * @access private
     * @param array $option 路由参数
     * @param string $url URL地址
     * @param \think\Request $request Request对象
     * @return bool
     */
    private static function checkOption($option, $url, $request)
    {
        // 请求类型检测
        if ((isset($option['method']) && false === stripos($option['method'], $request->method()))
            || (isset($option['ext']) && false === stripos($option['ext'], $request->ext())) // 伪静态后缀检测
             || (isset($option['domain']) && !in_array($option['domain'], [$_SERVER['HTTP_HOST'], self::$subDomain])) // 域名检测
             || (!empty($option['https']) && !$request->isSsl()) // https检测
             || (!empty($option['before_behavior']) && false === Hook::exec($option['before_behavior'], '', $url)) // 行为检测
             || (!empty($option['callback']) && is_callable($option['callback']) && false === call_user_func($option['callback'])) // 自定义检测
        ) {
            return false;
        }
        return true;
    }

    /**
     * 检测路由规则
     * @access private
     * @param string $rule 路由规则
     * @param string $url URL地址
     * @param string $route 路由地址
     * @param array $pattern 变量规则
     * @param array $option 路由参数
     * @return array|false
     */
    private static function checkRule($rule, $route, $url, $pattern, $option)
    {
        // 检查完整规则定义
        if (isset($pattern['__url__']) && !preg_match('/^' . $pattern['__url__'] . '/', $url)) {
            return false;
        }
        // 检测是否设置了参数分隔符
        if ($depr = Config::get('url_params_depr')) {
            $url  = str_replace($depr, '/', $url);
            $rule = str_replace($depr, '/', $rule);
        }

        $rule = ltrim($rule, '/');
        $len1 = substr_count($url, '/');
        $len2 = substr_count($rule, '/');
        // 多余参数是否合并
        $merge = !empty($option['merge_extra_vars']) ? true : false;

        if ($len1 >= $len2 || strpos($rule, '[')) {
            if ('$' == substr($rule, -1, 1)) {
                // 完整匹配
                if (!$merge && $len1 != $len2 && false === strpos($rule, '[')) {
                    return false;
                } else {
                    $rule = substr($rule, 0, -1);
                }
            }
            $pattern = array_merge(self::$pattern, $pattern);
            if (false !== $match = self::match($url, $rule, $pattern, $merge)) {
                // 匹配到路由规则
                // 检测是否定义路由
                if (!empty($option['after_behavior'])) {
                    if ($option['after_behavior'] instanceof \Closure) {
                        $result = call_user_func_array($option['after_behavior'], [$route]);
                    } else {
                        $result = Hook::exec($option['after_behavior'], '', $route);
                    }
                    // 路由规则重定向
                    if ($result instanceof Response) {
                        return ['type' => 'response', 'response' => $result, 'params' => $match];
                    } elseif (is_array($result)) {
                        return $result;
                    }
                }
                if ($route instanceof \Closure) {
                    // 执行闭包
                    return ['type' => 'function', 'function' => $route, 'params' => $match];
                }
                return self::parseRule($rule, $route, $url, $match, $merge);
            }
        }
        return false;
    }

    /**
     * 解析模块的URL地址 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access public
     * @param string $url URL地址
     * @param string $depr URL分隔符
     * @param bool $autoSearch 是否自动深度搜索控制器
     * @param integer $paramType URL参数解析方式 0 名称解析 1 顺序解析
     * @return array
     */
    public static function parseUrl($url, $depr = '/', $autoSearch = false, $paramType = 0)
    {
        if (isset(self::$bind['module'])) {
            // 如果有模块/控制器绑定
            $url = self::$bind['module'] . '/' . $url;
        }
        // 分隔符替换 确保路由定义使用统一的分隔符
        if ('/' != $depr) {
            $url = str_replace($depr, '/', $url);
        }

        $result = self::parseRoute($url, $autoSearch, true, $paramType);

        if (!empty($result['var'])) {
            $_GET = array_merge($result['var'], $_GET);
        }
        return ['type' => 'module', 'module' => $result['route']];
    }

    /**
     * 解析规范的路由地址 地址格式 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access private
     * @param string $url URL地址
     * @param bool $autoSearch 是否自动深度搜索控制器
     * @param bool $reverse 是否反转解析URL
     * @param integer $paramType URL参数解析方式 0 名称解析 1 顺序解析
     * @return array
     */
    private static function parseRoute($url, $autoSearch = false, $reverse = false, $paramType = 0)
    {
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
        $route = [null, null, null];
        if (isset($path)) {
            if ($reverse) {
                // 解析模块
                $module = APP_MULTI_MODULE ? array_shift($path) : null;
                if ($autoSearch) {
                    // 自动搜索控制器
                    $dir    = APP_PATH . ($module ? $module . DS : '') . 'controller';
                    $suffix = CLASS_APPEND_SUFFIX || Config::get('use_controller_suffix') ? 'Controller' : '';
                    $item   = [];
                    foreach ($path as $val) {
                        $item[] = array_shift($path);
                        if (is_file($dir . DS . $val . $suffix . EXT)) {
                            break;
                        } else {
                            $dir .= DS . $val;
                        }
                    }
                    $controller = implode('.', $item);
                } else {
                    // 解析控制器
                    $controller = !empty($path) ? array_shift($path) : null;
                }
                // 解析操作
                $action = !empty($path) ? array_shift($path) : null;
                // 解析额外参数
                if (!empty($path)) {
                    if ($paramType) {
                        $var += $path;
                    } else {
                        preg_replace_callback('/([^\/]+)\/([^\/]+)/', function ($match) use (&$var) {
                            $var[strtolower($match[1])] = strip_tags($match[2]);
                        }, implode('/', $path));
                    }
                }
            } else {
                $action     = array_pop($path);
                $controller = !empty($path) ? array_pop($path) : null;
                $module     = APP_MULTI_MODULE && !empty($path) ? array_pop($path) : null;
                $method     = Request::instance()->method();
                // REST 操作方法支持
                if ('[rest]' == $action) {
                    $action = $method;
                } elseif (Config::get('use_action_prefix') && !empty(self::$methodPrefix[$method])) {
                    // 操作方法前缀支持
                    $action = 0 !== strpos($action, self::$methodPrefix[$method]) ? self::$methodPrefix[$method] . $action : $action;
                }
            }
            // 封装路由
            $route = [$module, $controller, $action];
        }
        return ['route' => $route, 'var' => $var];
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param string $url URL地址
     * @param string $rule 路由规则
     * @param array $pattern 变量规则
     * @param bool $merge 合并额外变量
     * @return array|false
     */
    private static function match($url, $rule, $pattern, $merge)
    {
        $m2 = explode('/', $rule);
        $m1 = $merge ? explode('/', $url, count($m2)) : explode('/', $url);

        $var = [];
        foreach ($m2 as $key => $val) {
            // val中定义了多个变量 <id><name>
            if (false !== strpos($val, '<') && preg_match_all('/<(\w+(\??))>/', $val, $matches)) {
                $value = [];
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name      = substr($name, 0, -1);
                        $replace[] = '((' . (isset($pattern[$name]) ? $pattern[$name] : '') . ')?)';
                    } else {
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '') . ')';
                    }
                    $value[] = $name;
                }
                $val = str_replace($matches[0], $replace, $val);
                if (preg_match('/^' . $val . '$/', $m1[$key], $match)) {
                    array_shift($match);
                    $match = array_slice($match, 0, count($value));
                    $var   = array_merge($var, array_combine($value, $match));
                    continue;
                } else {
                    return false;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $val = substr($val, 1, -1);
            }
            if (0 === strpos($val, ':')) {
                // URL变量
                $name = substr($val, 1);
                if (isset($m1[$key]) && isset($pattern[$name]) && !preg_match('/^' . $pattern[$name] . '$/', $m1[$key])) {
                    // 检查变量规则
                    return false;
                }
                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

    /**
     * 解析规则路由
     * @access private
     * @param string $rule 路由规则
     * @param string $route 路由地址
     * @param string $pathinfo URL地址
     * @param array $matches 匹配的变量
     * @param bool $merge 合并额外变量
     * @return array
     */
    private static function parseRule($rule, $route, $pathinfo, $matches, $merge = false)
    {
        // 解析路由规则
        $rule = explode('/', $rule);
        // 获取URL地址中的参数
        $paths = $merge ? explode('/', $pathinfo, count($rule)) : explode('/', $pathinfo);
        // 获取路由地址规则
        $url = is_array($route) ? $route[0] : $route;

        foreach ($rule as $item) {
            $fun = '';
            if (0 === strpos($item, '[:')) {
                $item = substr($item, 1, -1);
            }
            if (0 === strpos($item, ':')) {
                $var           = substr($item, 1);
                $matches[$var] = array_shift($paths);
            } else {
                // 过滤URL中的静态变量
                array_shift($paths);
            }
        }
        // 替换路由地址中的变量
        foreach ($matches as $key => $val) {
            if (false !== strpos($url, ':' . $key)) {
                $url = str_replace(':' . $key, $val, $url);
                unset($matches[$key]);
            }
        }
        if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
            // 路由到重定向地址
            $result = ['type' => 'redirect', 'url' => $url, 'status' => (is_array($route) && isset($route[1])) ? $route[1] : 301];
        } elseif (0 === strpos($url, '\\')) {
            // 路由到方法
            $method = strpos($url, '@') ? explode('@', $url) : $url;
            $result = ['type' => 'method', 'method' => $method, 'params' => $matches];
        } elseif (0 === strpos($url, '@')) {
            // 路由到控制器
            $result = ['type' => 'controller', 'controller' => substr($url, 1), 'params' => $matches];
        } else {
            // 解析路由地址
            $result = self::parseRoute($url);
            $var    = array_merge($matches, $result['var']);
            // 解析剩余的URL参数
            self::parseUrlParams(implode('/', $paths), $var);
            // 路由到模块/控制器/操作
            $result = ['type' => 'module', 'module' => $result['route']];
            // 路由地址中的控制器和操作关闭自动转换
            Config::set('url_controller_convert', false);
            Config::set('url_action_convert', false);
        }
        return $result;
    }

    /**
     * 解析URL地址中的参数到$_GET
     * @access private
     * @param string $rule 路由规则
     * @param array $var 变量
     * @return void
     */
    private static function parseUrlParams($url, $var = [])
    {
        if ($url) {
            if (Config::get('url_param_type')) {
                $var += explode('/', $url);
            } else {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                    $var[strtolower($match[1])] = strip_tags($match[2]);
                }, $url);
            }
        }
        $_GET = array_merge($var, $_GET);
    }

}
