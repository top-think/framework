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

namespace think\route;

use think\Route;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;
use think\route\dispatch\Module as ModuleDispatch;
use think\route\dispatch\Redirect as RedirectDispatch;

class RouteRule extends Rule
{
    // 路由规则
    protected $rule;
    // 路由标识
    protected $name;
    // 路由地址
    protected $route;
    // 请求类型
    protected $method;

    // 路由匹配模式
    protected $completeMatch = false;
    // 不同请求类型的方法前缀
    protected $methodPrefix = [
        'get'    => 'get',
        'post'   => 'post',
        'put'    => 'put',
        'delete' => 'delete',
        'patch'  => 'patch',
    ];

    /**
     * 架构函数
     * @access public
     * @param string|array      $rule 路由规则
     * @param string|\Closure   $route 路由地址
     * @param string            $method 请求类型
     * @param array             $option 路由参数
     * @param array             $pattern 变量规则
     */
    public function __construct(Route $router, $rule, $route, $method = '*', $option = [], $pattern = [])
    {
        $this->router = $router;
        $this->group  = $router->getCurrentGroup();

        $this->setRule($rule, $option);

        $this->setMethod($method);

        $this->route   = $route;
        $this->option  = $option;
        $this->pattern = $pattern;
    }

    public function setRule($rule, $option = [])
    {
        if ('$' == substr($rule, -1, 1)) {
            // 是否完整匹配
            $rule = substr($rule, 0, -1);

            $this->completeMatch = true;
        } elseif (!empty($option['complete_match'])) {
            $this->completeMatch = true;
        }

        $this->rule = trim($rule, '/');
    }

    public function setName($name, $vars = [], $option = [])
    {

        $key = !$this->group->isEmpty() ? $this->group->getName() . ($rule ? '/' . $rule : '') : $rule;

        $suffix = isset($option['ext']) ? $option['ext'] : null;

        $this->name[strtolower($name)] = [$key, $vars, $this->group->domain(), $suffix];

    }

    public function setMethod($method, &$option = [])
    {
        $method = strtolower($method);

        if (strpos($method, '|')) {
            $option['method'] = $method;
            $method           = '*';
        }

        $this->method = $method;
    }

    // 检测路由规则
    public function check($request, $url, $depr = '/')
    {
        // 检查参数有效性
        if (!$this->checkOption($this->option, $request)) {
            return false;
        }

        if (isset($this->option['ext'])) {
            // 路由ext参数 优先于系统配置的URL伪静态后缀参数
            $url = preg_replace('/\.' . $request->ext() . '$/i', '', $url);
        }

        return $this->checkRule($request, $url, $depr);
    }

    /**
     * 检测路由规则
     * @access private
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @param array     $option 路由参数
     * @param string    $depr URL分隔符（全局）
     * @return array|false
     */
    private function checkRule($request, $url, $depr)
    {
        // 检查完整规则定义
        if (isset($this->pattern['__url__']) && !preg_match('/^' . $this->pattern['__url__'] . '/', str_replace('|', $depr, $url))) {
            return false;
        }

        // 检查路由的参数分隔符
        if (isset($this->option['param_depr'])) {
            $url = str_replace(['|', $this->option['param_depr']], [$depr, '|'], $url);
        }

        $len1 = substr_count($url, '|');
        $len2 = substr_count($this->rule, '/');

        // 多余参数是否合并
        $merge = !empty($this->option['merge_extra_vars']) ? true : false;

        if ($merge && $len1 > $len2) {
            $url = str_replace('|', $depr, $url);
            $url = implode('|', explode($depr, $url, $len2 + 1));
        }

        if ($len1 >= $len2 || strpos($this->rule, '[')) {
            if (!empty($this->option['complete_match'])) {
                // 完整匹配
                if (!$merge && $len1 != $len2 && (false === strpos($this->rule, '[') || $len1 > $len2 || $len1 < $len2 - substr_count($this->rule, '['))) {
                    return false;
                }
            }

            $pattern = array_merge($this->router->pattern(), $this->pattern);

            if (false !== $match = $this->match($url, $pattern)) {
                // 匹配到路由规则
                return $this->parseRule($request, $this->rule, $this->route, $url, $this->option, $match);
            }
        }

        return false;
    }

    /**
     * 检测URL和规则路由是否匹配
     * @access private
     * @param string    $url URL地址
     * @param array     $pattern 变量规则
     * @return array|false
     */
    private function match($url, $pattern)
    {
        $m2 = explode('/', $this->rule);
        $m1 = explode('|', $url);

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

                if (isset($m1[$key]) && isset($pattern[$name])) {
                    // 检查变量规则
                    if ($pattern[$name] instanceof \Closure) {
                        $result = call_user_func_array($pattern[$name], [$m1[$key]]);
                        if (false === $result) {
                            return false;
                        }
                    } elseif (!preg_match('/^' . $pattern[$name] . '$/', $m1[$key])) {
                        return false;
                    }
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
     * @return Dispatch
     */
    private function parseRule($request, $rule, $route, $pathinfo, $option = [], $matches = [])
    {

        // 解析路由规则
        if ($rule) {
            $rule = explode('/', $rule);
            // 获取URL地址中的参数
            $paths = explode('|', $pathinfo);

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
            $paths = explode('|', $pathinfo);
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

        // 开启请求缓存
        if ($request->isGet() && isset($option['cache'])) {
            $cache = $option['cache'];

            if (is_array($cache)) {
                list($key, $expire) = $cache;
            } else {
                $key    = str_replace('|', '/', $pathinfo);
                $expire = $cache;
            }

            $request->cache($key, $expire);
        }

        // 解析额外参数
        $this->parseUrlParams(empty($paths) ? '' : implode('|', $paths), $matches);
        // 记录匹配的路由信息
        $request->routeInfo(['rule' => $rule, 'route' => $route, 'option' => $option, 'var' => $matches]);

        // 检测路由after行为
        if (!empty($option['after_behavior'])) {
            if ($option['after_behavior'] instanceof \Closure) {
                $result = call_user_func_array($option['after_behavior'], []);
            } else {
                foreach ((array) $option['after_behavior'] as $behavior) {
                    $result = $this->app['hook']->exec($behavior, '');

                    if (!is_null($result)) {
                        break;
                    }
                }
            }

            // 路由规则重定向
            if ($result instanceof Response) {
                return ['type' => 'response', 'response' => $result];
            } elseif ($result instanceof Dispatch) {
                return $result;
            }
        }

        if ($route instanceof \Closure) {
            // 执行闭包
            $result = new CallbackDispatch($route);
        } elseif (0 === strpos($route, '/') || strpos($route, '://')) {
            // 路由到重定向地址
            $result = new RedirectDispatch($route, [], isset($option['status']) ? $option['status'] : 301);
        } elseif (false !== strpos($route, '\\')) {
            // 路由到方法
            list($path, $var) = $this->parseUrlPath($route);
            $route            = str_replace('/', '@', implode('/', $path));
            $method           = strpos($route, '@') ? explode('@', $route) : $route;
            $result           = new CallbackDispatch($method, $var);
        } elseif (0 === strpos($route, '@')) {
            // 路由到控制器
            $route             = substr($route, 1);
            list($route, $var) = $this->parseUrlPath($route);
            $result            = new ControllerDispatch(implode('/', $route), $var);

            $request->action(array_pop($route));
            $request->controller($route ? array_pop($route) : $this->app->config('default_controller'));
            $request->module($route ? array_pop($route) : $this->app->config('default_module'));
            $this->app->setModulePath($this->app->getAppPath() . ($this->app->config('app_multi_module') ? $request->module() . DIRECTORY_SEPARATOR : ''));
        } else {
            // 路由到模块/控制器/操作
            $result = $this->parseModule($route);
        }

        return $result;
    }

    /**
     * 解析URL地址为 模块/控制器/操作
     * @access private
     * @param string    $url URL地址
     * @return array
     */
    private function parseModule($url)
    {
        list($path, $var) = $this->parseUrlPath($url);

        $action     = array_pop($path);
        $controller = !empty($path) ? array_pop($path) : null;
        $module     = $this->app['config']->get('app_multi_module') && !empty($path) ? array_pop($path) : null;
        $method     = $this->app['request']->method();

        if ($this->app['config']->get('use_action_prefix') && !empty($this->methodPrefix[$method])) {
            // 操作方法前缀支持
            $action = 0 !== strpos($action, $this->methodPrefix[$method]) ? $this->methodPrefix[$method] . $action : $action;
        }

        // 设置当前请求的路由变量
        $this->app['request']->route($var);

        // 路由到模块/控制器/操作
        return new ModuleDispatch([$module, $controller, $action]);
    }

}
