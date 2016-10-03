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

use think\App;
use think\Config;
use think\exception\HttpException;
use think\Hook;
use think\Loader;
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
        'PATCH'   => [],
        'HEAD'    => [],
        'OPTIONS' => [],
        '*'       => [],
        'alias'   => [],
        'domain'  => [],
        'pattern' => [],
        'name'    => [],
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

    // 子域名
    private static $subDomain = '';
    // 域名绑定
    private static $bind = [];
    // 当前分组信息
    private static $group = [];
    // 当前子域名绑定
    private static $domainBind;
    private static $domainRule;
    // 当前域名
    private static $domain;

    /**
     * 注册变量规则
     * @access public
     * @param string|array  $name 变量名
     * @param string        $rule 变量规则
     * @return void
     */
    public static function pattern($name = null, $rule = '')
    {
        if (is_array($name)) {
            self::$rules['pattern'] = array_merge(self::$rules['pattern'], $name);
        } else {
            self::$rules['pattern'][$name] = $rule;
        }
    }

    /**
     * 注册子域名部署规则
     * @access public
     * @param string|array  $domain 子域名
     * @param mixed         $rule 路由规则
     * @param array         $option 路由参数
     * @param array         $pattern 变量规则
     * @return void
     */
    public static function domain($domain, $rule = '', $option = [], $pattern = [])
    {
        if (is_array($domain)) {
            foreach ($domain as $key => $item) {
                self::domain($key, $item, $option, $pattern);
            }
        } elseif ($rule instanceof \Closure) {
            // 执行闭包
            self::setDomain($domain);
            call_user_func_array($rule, []);
            self::setDomain(null);
        } elseif (is_array($rule)) {
            self::setDomain($domain);
            self::group('', function () use ($rule) {
                // 动态注册域名的路由规则
                self::registerRules($rule);
            }, $option, $pattern);
            self::setDomain(null);
        } else {
            self::$rules['domain'][$domain]['[bind]'] = [$rule, $option, $pattern];
        }
    }

    private static function setDomain($domain)
    {
        self::$domain = $domain;
    }

    /**
     * 设置路由绑定
     * @access public
     * @param mixed     $bind 绑定信息
     * @param string    $type 绑定类型 默认为module 支持 namespace class
     * @return mixed
     */
    public static function bind($bind, $type = 'module')
    {
        self::$bind = ['type' => $type, $type => $bind];
    }

    /**
     * 设置或者获取路由标识
     * @access public
     * @param string|array     $name 路由命名标识 数组表示批量设置
     * @param array            $value 路由地址及变量信息
     * @return array
     */
    public static function name($name = '', $value = null)
    {
        if (is_array($name)) {
            return self::$rules['name'] = $name;
        } elseif ('' === $name) {
            return self::$rules['name'];
        } elseif (!is_null($value)) {
            self::$rules['name'][$name][] = $value;
        } else {
            return isset(self::$rules['name'][$name]) ? self::$rules['name'][$name] : null;
        }
    }

    /**
     * 读取路由绑定
     * @access public
     * @param string    $type 绑定类型
     * @return mixed
     */
    public static function getBind($type)
    {
        return isset(self::$bind[$type]) ? self::$bind[$type] : null;
    }

    /**
     * 导入配置文件的路由规则
     * @access public
     * @param array     $rule 路由规则
     * @param string    $type 请求类型
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

        self::registerRules($rule, strtoupper($type));
    }

    // 批量注册路由
    protected static function registerRules($rules, $type = '*')
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
                self::group($key, $val);
            } elseif (is_array($val)) {
                self::setRule($key, $val[0], $type, $val[1], isset($val[2]) ? $val[2] : []);
            } else {
                self::setRule($key, $val, $type);
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
    public static function rule($rule, $route = '', $type = '*', $option = [], $pattern = [])
    {
        $group = self::getGroup('name');
        if (!is_null($group)) {
            // 路由分组
            $option  = array_merge(self::getGroup('option'), $option);
            $pattern = array_merge(self::getGroup('pattern'), $pattern);
        }

        $type = strtoupper($type);

        if (strpos($type, '|')) {
            $option['method'] = $type;
            $type             = '*';
        }
        if (is_array($rule) && empty($route)) {
            foreach ($rule as $key => $val) {
                if (is_numeric($key)) {
                    $key = array_shift($val);
                }
                if (is_array($val)) {
                    $route    = $val[0];
                    $option1  = array_merge($option, $val[1]);
                    $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                } else {
                    $route = $val;
                }
                self::setRule($key, $route, $type, isset($option1) ? $option1 : $option, isset($pattern1) ? $pattern1 : $pattern, $group);
            }
        } else {
            self::setRule($rule, $route, $type, $option, $pattern, $group);
        }

    }

    /**
     * 设置路由规则
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $type 请求类型
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @param string    $group 所属分组
     * @return void
     */
    protected static function setRule($rule, $route, $type = '*', $option = [], $pattern = [], $group = '')
    {
        if (is_array($rule)) {
            $name = $rule[0];
            $rule = $rule[1];
        } elseif (is_string($route)) {
            $name = $route;
        }
        if (!isset($option['complete_match'])) {
            if (Config::get('route_complete_match')) {
                $option['complete_match'] = true;
            } elseif ('$' == substr($rule, -1, 1)) {
                // 是否完整匹配
                $option['complete_match'] = true;
                $rule                     = substr($rule, 0, -1);
            }
        } elseif (empty($option['complete_match']) && '$' == substr($rule, -1, 1)) {
            // 是否完整匹配
            $option['complete_match'] = true;
            $rule                     = substr($rule, 0, -1);
        }

        if ('/' != $rule) {
            $rule = trim($rule, '/');
        }
        $vars = self::parseVar($rule);
        if (isset($name)) {
            self::name($name, [$rule, $vars, self::$domain]);
        }
        if ($group) {
            if ('*' != $type) {
                $option['method'] = $type;
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain]['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules['*'][$group]['rule'][] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
        } else {
            if ('*' != $type && isset(self::$rules['*'][$rule])) {
                unset(self::$rules['*'][$rule]);
            }
            if (self::$domain) {
                self::$rules['domain'][self::$domain][$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            } else {
                self::$rules[$type][$rule] = ['rule' => $rule, 'route' => $route, 'var' => $vars, 'option' => $option, 'pattern' => $pattern];
            }
            if ('*' == $type) {
                // 注册路由快捷方式
                foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'] as $method) {
                    if (self::$domain) {
                        self::$rules['domain'][self::$domain][$method][$rule] = true;
                    } else {
                        self::$rules[$method][$rule] = true;
                    }
                }
            }
        }
    }

    /**
     * 获取当前的分组信息
     * @access public
     * @param string    $type 分组信息名称 name option pattern
     * @return mixed
     */
    public static function getGroup($type)
    {
        if (isset(self::$group[$type])) {
            return self::$group[$type];
        } else {
            return 'name' == $type ? null : [];
        }
    }

    /**
     * 设置当前的路由分组
     * @access public
     * @param string    $name 分组名称
     * @param array     $option 分组路由参数
     * @param array     $pattern 分组变量规则
     * @return void
     */
    public static function setGroup($name, $option = [], $pattern = [])
    {
        self::$group['name']    = $name;
        self::$group['option']  = $option ?: [];
        self::$group['pattern'] = $pattern ?: [];
    }

    /**
     * 注册路由分组
     * @access public
     * @param string|array      $name 分组名称或者参数
     * @param array|\Closure    $routes 路由地址
     * @param array             $option 路由参数
     * @param array             $pattern 变量规则
     * @return void
     */
    public static function group($name, $routes, $option = [], $pattern = [])
    {
        if (is_array($name)) {
            $option = $name;
            $name   = isset($option['name']) ? $option['name'] : '';
        }
        // 分组
        $currentGroup = self::getGroup('name');
        if ($currentGroup) {
            $name = $currentGroup . ($name ? '/' . ltrim($name, '/') : '');
        }
        if (!empty($name)) {
            if ($routes instanceof \Closure) {
                $currentOption  = self::getGroup('option');
                $currentPattern = self::getGroup('pattern');
                self::setGroup($name, array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
                call_user_func_array($routes, []);
                self::setGroup($currentGroup, $currentOption, $currentPattern);
                if ($currentGroup != $name) {
                    self::$rules['*'][$name]['route']   = '';
                    self::$rules['*'][$name]['var']     = self::parseVar($name);
                    self::$rules['*'][$name]['option']  = $option;
                    self::$rules['*'][$name]['pattern'] = $pattern;
                }
            } else {
                $item = [];
                foreach ($routes as $key => $val) {
                    if (is_numeric($key)) {
                        $key = array_shift($val);
                    }
                    if (is_array($val)) {
                        $route    = $val[0];
                        $option1  = array_merge($option, isset($val[1]) ? $val[1] : []);
                        $pattern1 = array_merge($pattern, isset($val[2]) ? $val[2] : []);
                    } else {
                        $route = $val;
                    }

                    $options  = isset($option1) ? $option1 : $option;
                    $patterns = isset($pattern1) ? $pattern1 : $pattern;
                    if ('$' == substr($key, -1, 1)) {
                        // 是否完整匹配
                        $options['complete_match'] = true;
                        $key                       = substr($key, 0, -1);
                    }
                    $vars   = self::parseVar($key);
                    $item[] = ['rule' => $key, 'route' => $route, 'var' => $vars, 'option' => $options, 'pattern' => $patterns];
                    // 设置路由标识
                    self::name($route, [$key, $vars, self::$domain]);
                }
                self::$rules['*'][$name] = ['rule' => $item, 'route' => '', 'var' => [], 'option' => $option, 'pattern' => $pattern];
            }

            foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'] as $method) {
                if (!isset(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = true;
                } elseif (is_array(self::$rules[$method][$name])) {
                    self::$rules[$method][$name] = array_merge(self::$rules['*'][$name], self::$rules[$method][$name]);
                }
            }

        } elseif ($routes instanceof \Closure) {
            // 闭包注册
            $currentOption  = self::getGroup('option');
            $currentPattern = self::getGroup('pattern');
            self::setGroup('', array_merge($currentOption, $option), array_merge($currentPattern, $pattern));
            call_user_func_array($routes, []);
            self::setGroup($currentGroup, $currentOption, $currentPattern);
        } else {
            // 批量注册路由
            self::rule($routes, '', '*', $option, $pattern);
        }
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
    public static function any($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, '*', $option, $pattern);
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
    public static function get($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'GET', $option, $pattern);
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
    public static function post($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'POST', $option, $pattern);
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
    public static function put($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PUT', $option, $pattern);
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
    public static function delete($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'DELETE', $option, $pattern);
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
    public static function patch($rule, $route = '', $option = [], $pattern = [])
    {
        self::rule($rule, $route, 'PATCH', $option, $pattern);
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
                if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);
                } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
                }
                $item = ltrim($rule . $val[1], '/');
                self::rule($item . '$', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }
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
    public static function controller($rule, $route = '', $option = [], $pattern = [])
    {
        foreach (self::$methodPrefix as $type => $val) {
            self::$type($rule . '/:action', $route . '/' . $val . ':action', $option, $pattern);
        }
    }

    /**
     * 注册别名路由
     * @access public
     * @param string|array  $rule 路由别名
     * @param string        $route 路由地址
     * @param array         $option 路由参数
     * @return void
     */
    public static function alias($rule = null, $route = '', $option = [])
    {
        if (is_array($rule)) {
            self::$rules['alias'] = array_merge(self::$rules['alias'], $rule);
        } else {
            self::$rules['alias'][$rule] = $option ? [$route, $option] : $route;
        }
    }

    /**
     * 设置不同请求类型下面的方法前缀
     * @access public
     * @param string    $method 请求类型
     * @param string    $prefix 类型前缀
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
     * @param string    $name 方法名称
     * @param array     $resourece 资源
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
     * @param string    $route 路由地址
     * @param string    $method 请求类型
     * @param array     $option 路由参数
     * @return void
     */
    public static function miss($route, $method = '*', $option = [])
    {
        self::rule('__miss__', $route, $method, $option, []);
    }

    /**
     * 注册一个自动解析的URL路由
     * @access public
     * @param string    $route 路由地址
     * @return void
     */
    public static function auto($route)
    {
        self::rule('__auto__', $route, '*', [], []);
    }

    /**
     * 获取或者批量设置路由定义
     * @access public
     * @param mixed $rules 请求类型或者路由定义数组
     * @return array
     */
    public static function rules($rules = '')
    {
        if (is_array($rules)) {
            self::$rules = $rules;
        } elseif ($rules) {
            return true === $rules ? self::$rules : self::$rules[$rules];
        } else {
            $rules = self::$rules;
            unset($rules['pattern'], $rules['alias'], $rules['domain'], $rules['name']);
            return $rules;
        }
    }

    /**
     * 检测子域名部署
     * @access public
     * @param Request   $request Request请求对象
     * @param array     $currentRules 当前路由规则
     * @param string    $method 请求类型
     * @return void
     */
    public static function checkDomain($request, &$currentRules, $method = 'GET')
    {
        // 域名规则
        $rules = self::$rules['domain'];
        // 开启子域名部署 支持二级和三级域名
        if (!empty($rules)) {
            $host = $request->host();
            if (isset($rules[$host])) {
                // 完整域名或者IP配置
                $item = $rules[$host];
            } else {
                $rootDomain = Config::get('url_domain_root');
                if ($rootDomain) {
                    // 配置域名根 例如 thinkphp.cn 163.com.cn 如果是国家级域名 com.cn net.cn 之类的域名需要配置
                    $domain = explode('.', rtrim(stristr($host, $rootDomain, true), '.'));
                } else {
                    $domain = explode('.', $host, -2);
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
                        $item = $rules[$subDomain];
                    } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                        // 泛三级域名
                        $item      = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    } elseif (isset($rules['*']) && !empty($domain2)) {
                        // 泛二级域名
                        if ('www' != $domain2) {
                            $item      = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
            }
            if (!empty($item)) {
                if (isset($item['[bind]'])) {
                    // 解析子域名部署规则
                    list($rule, $option, $pattern) = $item['[bind]'];
                    if (!empty($option['https']) && !$request->isSsl()) {
                        // https检测
                        throw new HttpException(404, 'must use https request:' . $host);
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
                        // 绑定到类 例如 @app\index\controller\User
                        self::$bind = ['type' => 'class', 'class' => substr($result, 1)];
                    } else {
                        // 绑定到模块/控制器 例如 index/user
                        self::$bind = ['type' => 'module', 'module' => $result];
                    }
                    self::$domainBind = true;
                } else {
                    self::$domainRule = $item;
                    $currentRules     = isset($item[$method]) ? $item[$method] : $item['*'];
                }
            }
        }
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
    public static function check($request, $url, $depr = '/', $checkDomain = false)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        if ('/' != $depr) {
            $url = str_replace($depr, '/', $url);
        }

        if (strpos($url, '/') && isset(self::$rules['alias'][strstr($url, '/', true)])) {
            // 检测路由别名
            $result = self::checkRouteAlias($request, $url, $depr);
            if (false !== $result) {
                return $result;
            }
        }
        $method = $request->method();
        // 获取当前请求类型的路由规则
        $rules = self::$rules[$method];
        // 检测域名部署
        if ($checkDomain) {
            self::checkDomain($request, $rules, $method);
        }
        // 检测URL绑定
        $return = self::checkUrlBind($url, $rules, $depr);
        if (false !== $return) {
            return $return;
        }
        if ('/' != $url) {
            $url = rtrim($url, '/');
        }
        if (isset($rules[$url])) {
            // 静态路由规则检测
            $rule = $rules[$url];
            if (true === $rule) {
                $rule = self::getRouteExpress($url);
            }
            if (!empty($rule['route']) && self::checkOption($rule['option'], $url, $request)) {
                return self::parseRule($url, $rule['route'], $url, $rule['option']);
            }
        }

        // 路由规则检测
        if (!empty($rules)) {
            return self::checkRoute($request, $rules, $url, $depr);
        }
        return false;
    }

    private static function getRouteExpress($key)
    {
        return self::$domainRule ? self::$domainRule['*'][$key] : self::$rules['*'][$key];
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
    private static function checkRoute($request, $rules, $url, $depr = '/', $group = '', $options = [])
    {
        foreach ($rules as $key => $item) {
            if (true === $item) {
                $item = self::getRouteExpress($key);
            }
            if (!isset($item['rule'])) {
                continue;
            }
            $rule    = $item['rule'];
            $route   = $item['route'];
            $vars    = $item['var'];
            $option  = $item['option'];
            $pattern = $item['pattern'];

            // 检查参数有效性
            if (!self::checkOption($option, $url, $request)) {
                continue;
            }

            if (isset($option['ext'])) {
                // 路由ext参数 优先于系统配置的URL伪静态后缀参数
                $url = preg_replace('/\.' . $request->ext() . '$/i', '', $url);
            }

            if (is_array($rule)) {
                // 分组路由
                $pos = strpos(str_replace('<', ':', $key), ':');
                if (false !== $pos) {
                    $str = substr($key, 0, $pos);
                } else {
                    $str = $key;
                }
                if (is_string($str) && $str && 0 !== strpos($url, $str)) {
                    continue;
                }

                $result = self::checkRoute($request, $rule, $url, $depr, $key, $option);
                if (false !== $result) {
                    return $result;
                }
            } elseif ($route) {
                if ('__miss__' == $rule || '__auto__' == $rule) {
                    // 指定特殊路由
                    $var    = trim($rule, '__');
                    ${$var} = $item;
                    continue;
                }
                if ($group) {
                    $rule = $group . ($rule ? '/' . ltrim($rule, '/') : '');
                }
                if (isset($options['bind_model']) && isset($option['bind_model'])) {
                    $option['bind_model'] = array_merge($options['bind_model'], $option['bind_model']);
                }
                $result = self::checkRule($rule, $route, $url, $pattern, $option);
                if (false !== $result) {
                    return $result;
                }
            }
        }
        if (isset($auto)) {
            // 自动解析URL地址
            return self::parseUrl($auto['route'] . '/' . $url, $depr);
        } elseif (isset($miss)) {
            // 未匹配所有路由的路由规则处理
            return self::parseRule('', $miss['route'], $url, $miss['option']);
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
    private static function checkRouteAlias($request, $url, $depr)
    {
        $array = explode('/', $url, 2);
        $item  = self::$rules['alias'][$array[0]];

        if (is_array($item)) {
            list($rule, $option) = $item;
        } else {
            $rule = $item;
        }
        // 参数有效性检查
        if (isset($option) && !self::checkOption($option, $url, $request)) {
            // 路由不匹配
            return false;
        } elseif (0 === strpos($rule, '\\')) {
            // 路由到类
            return self::bindToClass($array[1], substr($rule, 1), $depr);
        } elseif (0 === strpos($url, '@')) {
            // 路由到控制器类
            return self::bindToController($array[1], substr($rule, 1), $depr);
        } else {
            // 路由到模块/控制器
            return self::bindToModule($array[1], $rule, $depr);
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
    private static function checkUrlBind(&$url, &$rules, $depr = '/')
    {
        if (!empty(self::$bind)) {
            $type = self::$bind['type'];
            $bind = self::$bind[$type];
            // 记录绑定信息
            App::$debug && Log::record('[ BIND ] ' . var_export($bind, true), 'info');
            // 如果有URL绑定 则进行绑定检测
            switch ($type) {
                case 'class':
                    // 绑定到类
                    return self::bindToClass($url, $bind, $depr);
                case 'namespace':
                    // 绑定到命名空间
                    return self::bindToNamespace($url, $bind, $depr);
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
    public static function bindToClass($url, $class, $depr = '/')
    {
        $array  = explode($depr, $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'method', 'method' => [$class, $action]];
    }

    /**
     * 绑定到命名空间
     * @access public
     * @param string    $url URL地址
     * @param string    $namespace 命名空间
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToNamespace($url, $namespace, $depr = '/')
    {
        $array  = explode($depr, $url, 3);
        $class  = !empty($array[0]) ? $array[0] : Config::get('default_controller');
        $method = !empty($array[1]) ? $array[1] : Config::get('default_action');
        if (!empty($array[2])) {
            self::parseUrlParams($array[2]);
        }
        return ['type' => 'method', 'method' => [$namespace . '\\' . $class, $method]];
    }

    /**
     * 绑定到控制器类
     * @access public
     * @param string    $url URL地址
     * @param string    $controller 控制器名 （支持带模块名 index/user ）
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToController($url, $controller, $depr = '/')
    {
        $array  = explode($depr, $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'controller', 'controller' => $controller . '/' . $action];
    }

    /**
     * 绑定到模块/控制器
     * @access public
     * @param string    $url URL地址
     * @param string    $class 控制器类名（带命名空间）
     * @param string    $depr URL分隔符
     * @return array
     */
    public static function bindToModule($url, $controller, $depr = '/')
    {
        $array  = explode($depr, $url, 2);
        $action = !empty($array[0]) ? $array[0] : Config::get('default_action');
        if (!empty($array[1])) {
            self::parseUrlParams($array[1]);
        }
        return ['type' => 'module', 'module' => $controller . '/' . $action];
    }

    /**
     * 路由参数有效性检查
     * @access private
     * @param array     $option 路由参数
     * @param string    $url URL地址
     * @param Request   $request Request对象
     * @return bool
     */
    private static function checkOption($option, $url, $request)
    {
        // 请求类型检测
        if ((isset($option['method']) && false === stripos($option['method'], $request->method()))
            || (isset($option['ext']) && false === stripos($option['ext'], $request->ext())) // 伪静态后缀检测
             || (isset($option['deny_ext']) && false !== stripos($option['deny_ext'], $request->ext()))
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
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @param array     $option 路由参数
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

        $len1 = substr_count($url, '/');
        $len2 = substr_count($rule, '/');
        // 多余参数是否合并
        $merge = !empty($option['merge_extra_vars']) ? true : false;

        if ($len1 >= $len2 || strpos($rule, '[')) {
            if (!empty($option['complete_match'])) {
                // 完整匹配
                if (!$merge && $len1 != $len2 && (false === strpos($rule, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($rule, '['))) {
                    return false;
                }
            }
            $pattern = array_merge(self::$rules['pattern'], $pattern);
            if (false !== $match = self::match($url, $rule, $pattern, $merge)) {
                // 匹配到路由规则
                return self::parseRule($rule, $route, $url, $option, $match, $merge);
            }
        }
        return false;
    }

    /**
     * 解析模块的URL地址 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access public
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $autoSearch 是否自动深度搜索控制器
     * @return array
     */
    public static function parseUrl($url, $depr = '/', $autoSearch = false)
    {
        if (isset(self::$bind['module'])) {
            // 如果有模块/控制器绑定
            $url = self::$bind['module'] . '/' . ltrim($url, '/');
        }

        list($path, $var) = self::parseUrlPath($url, $depr);
        $route            = [null, null, null];
        if (isset($path)) {
            // 解析模块
            $module = Config::get('app_multi_module') ? array_shift($path) : null;
            if ($autoSearch) {
                // 自动搜索控制器
                $dir    = APP_PATH . ($module ? $module . DS : '') . Config::get('url_controller_layer');
                $suffix = App::$suffix || Config::get('controller_suffix') ? ucfirst(Config::get('url_controller_layer')) : '';
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
            self::parseUrlParams(empty($path) ? '' : implode('/', $path));
            // 封装路由
            $route = [$module, $controller, $action];
            if (isset(self::$rules['name'][implode($depr, $route)])) {
                throw new HttpException(404, 'invalid request:' . $url);
            }
        }
        return ['type' => 'module', 'module' => $route];
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access private
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return array
     */
    private static function parseUrlPath($url, $depr = '/')
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        if ('/' != $depr) {
            $url = str_replace($depr, '/', $url);
        }
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
     * 检测URL和规则路由是否匹配
     * @access private
     * @param string    $url URL地址
     * @param string    $rule 路由规则
     * @param array     $pattern 变量规则
     * @param bool      $merge 合并额外变量
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
                $value   = [];
                $replace = [];
                foreach ($matches[1] as $name) {
                    if (strpos($name, '?')) {
                        $name      = substr($name, 0, -1);
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')?';
                    } else {
                        $replace[] = '(' . (isset($pattern[$name]) ? $pattern[$name] : '\w+') . ')';
                    }
                    $value[] = $name;
                }
                $val = str_replace($matches[0], $replace, $val);
                if (preg_match('/^' . $val . '$/', isset($m1[$key]) ? $m1[$key] : '', $match)) {
                    array_shift($match);
                    foreach ($value as $k => $name) {
                        if (isset($match[$k])) {
                            $var[$name] = $match[$k];
                        }
                    }
                    continue;
                } else {
                    return false;
                }
            }

            if (0 === strpos($val, '[:')) {
                // 可选参数
                $val      = substr($val, 1, -1);
                $optional = true;
            } else {
                $optional = false;
            }
            if (0 === strpos($val, ':')) {
                // URL变量
                $name = substr($val, 1);
                if (!$optional && !isset($m1[$key])) {
                    return false;
                }
                if (isset($m1[$key]) && isset($pattern[$name]) && !preg_match('/^' . $pattern[$name] . '$/', $m1[$key])) {
                    // 检查变量规则
                    return false;
                }
                $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
            } elseif (!isset($m1[$key]) || 0 !== strcasecmp($val, $m1[$key])) {
                return false;
            }
        }
        // 成功匹配后返回URL中的动态变量数组
        return $var;
    }

    /**
     * 解析规则路由
     * @access private
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $pathinfo URL地址
     * @param array     $option 路由参数
     * @param array     $matches 匹配的变量
     * @param bool      $merge 合并额外变量
     * @return array
     */
    private static function parseRule($rule, $route, $pathinfo, $option = [], $matches = [], $merge = false)
    {
        $request = Request::instance();
        // 解析路由规则
        if ($rule) {
            $rule = explode('/', $rule);
            // 获取URL地址中的参数
            $paths = $merge ? explode('/', $pathinfo, count($rule)) : explode('/', $pathinfo);
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
        } else {
            $paths = explode('/', $pathinfo);
        }

        // 获取路由地址规则
        if (is_string($route) && isset($option['prefix'])) {
            // 路由地址前缀
            $route = $option['prefix'] . $route;
        }
        // 替换路由地址中的变量
        if (is_string($route) && !empty($matches)) {
            foreach ($matches as $key => $val) {
                if (false !== strpos($route, ':' . $key)) {
                    $route = str_replace(':' . $key, $val, $route);
                    unset($matches[$key]);
                }
            }
        }

        // 绑定模型数据
        if (isset($option['bind_model'])) {
            $bind = [];
            foreach ($option['bind_model'] as $key => $val) {
                if ($val instanceof \Closure) {
                    $result = call_user_func_array($val, [$matches]);
                } else {
                    if (is_array($val)) {
                        $fields    = explode('&', $val[1]);
                        $model     = $val[0];
                        $exception = isset($val[2]) ? $val[2] : true;
                    } else {
                        $fields    = ['id'];
                        $model     = $val;
                        $exception = true;
                    }
                    $where = [];
                    $match = true;
                    foreach ($fields as $field) {
                        if (!isset($matches[$field])) {
                            $match = false;
                            break;
                        } else {
                            $where[$field] = $matches[$field];
                        }
                    }
                    if ($match) {
                        $query  = strpos($model, '\\') ? $model::where($where) : Loader::model($model)->where($where);
                        $result = $query->failException($exception)->find();
                    }
                }
                if (!empty($result)) {
                    $bind[$key] = $result;
                }
            }
            $request->bind($bind);
        }

        // 解析额外参数
        self::parseUrlParams(empty($paths) ? '' : implode('/', $paths), $matches);
        // 记录匹配的路由信息
        $request->routeInfo(['rule' => $rule, 'route' => $route, 'option' => $option, 'var' => $matches]);

        // 检测路由after行为
        if (!empty($option['after_behavior'])) {
            if ($option['after_behavior'] instanceof \Closure) {
                $result = call_user_func_array($option['after_behavior'], []);
            } else {
                foreach ((array) $option['after_behavior'] as $behavior) {
                    $result = Hook::exec($behavior, '');
                    if (!is_null($result)) {
                        break;
                    }
                }
            }
            // 路由规则重定向
            if ($result instanceof Response) {
                return ['type' => 'response', 'response' => $result];
            } elseif (is_array($result)) {
                return $result;
            }
        }

        if ($route instanceof \Closure) {
            // 执行闭包
            $result = ['type' => 'function', 'function' => $route];
        } elseif (0 === strpos($route, '/') || 0 === strpos($route, 'http')) {
            // 路由到重定向地址
            $result = ['type' => 'redirect', 'url' => $route, 'status' => isset($option['status']) ? $option['status'] : 301];
        } elseif (false !== strpos($route, '\\')) {
            // 路由到方法
            $route  = str_replace('/', '@', $route);
            $method = strpos($route, '@') ? explode('@', $route) : $route;
            $result = ['type' => 'method', 'method' => $method];
        } elseif (0 === strpos($route, '@')) {
            // 路由到控制器
            $result = ['type' => 'controller', 'controller' => substr($route, 1)];
        } else {
            // 路由到模块/控制器/操作
            $result = self::parseModule($route);
        }
        // 开启请求缓存
        if ($request->isGet() && !empty($option['cache'])) {
            $cache = $option['cache'];
            if (is_array($cache)) {
                list($key, $expire) = $cache;
            } else {
                $key    = $pathinfo;
                $expire = $cache;
            }
            $request->cache($key, $expire);
        }
        return $result;
    }

    /**
     * 解析URL地址为 模块/控制器/操作
     * @access private
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @return array
     */
    private static function parseModule($url, $depr = '/')
    {
        list($path, $var) = self::parseUrlPath($url, $depr);
        $action           = array_pop($path);
        $controller       = !empty($path) ? array_pop($path) : null;
        $module           = Config::get('app_multi_module') && !empty($path) ? array_pop($path) : null;
        $method           = Request::instance()->method();
        if (Config::get('use_action_prefix') && !empty(self::$methodPrefix[$method])) {
            // 操作方法前缀支持
            $action = 0 !== strpos($action, self::$methodPrefix[$method]) ? self::$methodPrefix[$method] . $action : $action;
        }
        // 设置当前请求的路由变量
        Request::instance()->route($var);
        // 路由到模块/控制器/操作
        return ['type' => 'module', 'module' => [$module, $controller, $action], 'convert' => false];
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access private
     * @param string    $rule 路由规则
     * @param array     $var 变量
     * @return void
     */
    private static function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if (Config::get('url_param_type')) {
                $var += explode('/', $url);
            } else {
                preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }
        // 设置当前请求的参数
        Request::instance()->route($var);
    }

    // 分析路由规则中的变量
    private static function parseVar($rule)
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
