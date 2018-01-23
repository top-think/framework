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

namespace think\route;

use think\Container;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use think\route\dispatch\Callback as CallbackDispatch;
use think\route\dispatch\Controller as ControllerDispatch;
use think\route\dispatch\Module as ModuleDispatch;
use think\route\dispatch\Redirect as RedirectDispatch;
use think\route\dispatch\Response as ResponseDispatch;
use think\route\dispatch\View as ViewDispatch;

abstract class Rule
{
    protected $name;
    // 路由对象实例
    protected $router;
    // 路由父对象
    protected $parent;
    // 路由参数
    protected $option = [];
    // 路由变量规则
    protected $pattern = [];
    // 需要合并的路由参数
    protected $mergeOptions = ['after', 'before', 'model'];

    abstract public function check($request, $url, $depr = '/');

    /**
     * 注册路由参数
     * @access public
     * @param  string|array  $name  参数名
     * @param  mixed         $value 值
     * @return $this
     */
    public function option($name, $value = '')
    {
        if (is_array($name)) {
            $this->option = array_merge($this->option, $name);
        } else {
            $this->option[$name] = $value;
        }

        return $this;
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
        if (is_array($name)) {
            $this->pattern = array_merge($this->pattern, $name);
        } else {
            $this->pattern[$name] = $rule;
        }

        return $this;
    }

    /**
     * 设置Name
     * @access public
     * @param  string|array  $name 变量名
     * @return $this
     */
    public function name($name)
    {
        $this->name = '/' != $name ? ltrim($name, '/') : '/';

        return $this;
    }

    /**
     * 获取Name
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取变量规则定义
     * @access public
     * @param  string  $name 变量名
     * @return mixed
     */
    public function getPattern($name = '')
    {
        if ('' === $name) {
            return $this->pattern;
        }

        return isset($this->pattern[$name]) ? $this->pattern[$name] : null;
    }

    /**
     * 获取路由参数定义
     * @access public
     * @param  string  $name 参数名
     * @return mixed
     */
    public function getOption($name = '')
    {
        if ('' === $name) {
            return $this->option;
        }

        return isset($this->option[$name]) ? $this->option[$name] : null;
    }

    /**
     * 附加路由隐式参数
     * @access public
     * @param  array     $append
     * @return $this
     */
    public function append(array $append = [])
    {
        if (isset($this->option['append'])) {
            $this->option['append'] = array_merge($this->option['append'], $append);
        } else {
            $this->option['append'] = $append;
        }

        return $this;
    }

    /**
     * 设置路由请求类型
     * @access public
     * @param  string     $method
     * @return $this
     */
    public function method($method)
    {
        return $this->option('method', strtolower($method));
    }

    /**
     * 设置路由前置行为
     * @access public
     * @param  array|\Closure    $before
     * @return $this
     */
    public function before($before)
    {
        return $this->option('before', $before);
    }

    /**
     * 设置路由后置行为
     * @access public
     * @param  array|\Closure     $after
     * @return $this
     */
    public function after($after)
    {
        return $this->option('after', $after);
    }

    /**
     * 检查后缀
     * @access public
     * @param  string     $ext
     * @return $this
     */
    public function ext($ext = '')
    {
        return $this->option('ext', $ext);
    }

    /**
     * 检查禁止后缀
     * @access public
     * @param  string     $ext
     * @return $this
     */
    public function denyExt($ext = '')
    {
        return $this->option('deny_ext', $ext);
    }

    /**
     * 检查域名
     * @access public
     * @param  string     $domain
     * @return $this
     */
    public function domain($domain)
    {
        return $this->option('domain', $domain);
    }

    /**
     * 绑定模型
     * @access public
     * @param  array|string      $var  路由变量名 多个使用 & 分割
     * @param  string|\Closure   $model 绑定模型类
     * @param  bool              $exception 是否抛出异常
     * @return $this
     */
    public function model($var, $model = null, $exception = true)
    {
        if (is_array($var)) {
            $this->option['model'] = $var;
        } elseif (is_null($model)) {
            $this->option['model']['id'] = [$var, true];
        } else {
            $this->option['model'][$var] = [$model, $exception];
        }

        return $this;
    }

    /**
     * 绑定验证
     * @access public
     * @param  mixed    $validate 验证器类
     * @param  string   $scene 验证场景
     * @param  array    $message 验证提示
     * @param  bool     $batch 批量验证
     * @return $this
     */
    public function validate($validate, $scene = null, $message = [], $batch = false)
    {
        $this->option['validate'] = [$validate, $scene, $message, $batch];

        return $this;
    }

    /**
     * 绑定Response对象
     * @access public
     * @param  mixed     $response
     * @return $this
     */
    public function response($response)
    {
        return $this->option('response', $response);
    }

    /**
     * 设置Response Header信息
     * @access public
     * @param  string|array $name  参数名
     * @param  string       $value 参数值
     * @return $this
     */
    public function header($header, $value = null)
    {
        if (empty($this->option['header'])) {
            $this->option['header'] = [];
        }

        if (is_array($header)) {
            $this->option['header'] = array_merge($this->option['header'], $header);
        } else {
            $this->option['header'][$header] = $value;
        }

        return $this;
    }

    /**
     * 设置路由缓存
     * @access public
     * @param  array|string     $cache
     * @return $this
     */
    public function cache($cache)
    {
        return $this->option('cache', $cache);
    }

    /**
     * 检查URL分隔符
     * @access public
     * @param  bool     $depr
     * @return $this
     */
    public function depr($depr)
    {
        return $this->option('param_depr', $depr);
    }

    /**
     * 是否合并额外参数
     * @access public
     * @param  bool     $merge
     * @return $this
     */
    public function mergeExtraVars($merge = true)
    {
        return $this->option('merge_extra_vars', $merge);
    }

    /**
     * 设置需要合并的路由参数
     * @access public
     * @param  array     $option
     * @return $this
     */
    public function mergeOptions($option = [])
    {
        $this->mergeOptions = array_merge($this->mergeOptions, $option);
        return $this;
    }

    /**
     * 检查是否为HTTPS请求
     * @access public
     * @param  bool     $https
     * @return $this
     */
    public function https($https = true)
    {
        return $this->option('https', $https);
    }

    /**
     * 检查是否为AJAX请求
     * @access public
     * @param  bool     $ajax
     * @return $this
     */
    public function ajax($ajax = true)
    {
        return $this->option('ajax', $ajax);
    }

    /**
     * 检查是否为PJAX请求
     * @access public
     * @param  bool     $pjax
     * @return $this
     */
    public function pjax($pjax = true)
    {
        return $this->option('pjax', $pjax);
    }

    /**
     * 当前路由到一个模板地址 当使用数组的时候可以传入模板变量
     * @access public
     * @param  bool|array     $view
     * @return $this
     */
    public function view($view = true)
    {
        return $this->option('view', $view);
    }

    /**
     * 当前路由为重定向
     * @access public
     * @param  bool   $redirect 是否为重定向
     * @return $this
     */
    public function redirect($redirect = true)
    {
        return $this->option('redirect', $redirect);
    }

    /**
     * 设置路由完整匹配
     * @access public
     * @param  bool     $match
     * @return $this
     */
    public function completeMatch($match = true)
    {
        return $this->option('complete_match', $match);
    }

    /**
     * 是否去除URL最后的斜线
     * @access public
     * @param  bool     $remove
     * @return $this
     */
    public function removeSlash($remove = true)
    {
        return $this->option('remove_slash', $remove);
    }

    /**
     * 设置是否允许跨域
     * @access public
     * @param  bool     $allow
     * @param  array    $header
     * @return $this
     */
    public function allowCrossDomain($allow = true, $header = [])
    {
        if (!empty($header)) {
            $this->header($header);
        }

        if ($allow && $this->parent) {
            $this->parent->addRule($this, 'options');
        }

        return $this->option('cross_domain', $allow);
    }

    /**
     * 检查OPTIONS请求
     * @access public
     * @param  Request     $request
     * @return Dispatch|void
     */
    protected function checkCrossDomain($request)
    {
        if (!empty($this->option['cross_domain'])) {

            $header = [
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With',
            ];

            if (!empty($this->option['header'])) {
                $header = array_merge($header, $this->option['header']);
            }

            $this->option['header'] = $header;

            if ($request->method(true) == 'OPTIONS') {
                return new ResponseDispatch(Response::create()->code(204)->header($header));
            }
        }
    }

    /**
     * 设置路由规则全局有效
     * @access public
     * @return $this
     */
    public function crossDomainRule()
    {
        if ($this instanceof RuleGroup) {
            $method = '*';
        } else {
            $method = $this->method;
        }

        $this->router->setCrossDomainRule($this, $method);

        return $this;
    }

    /**
     * 合并分组参数
     * @access protected
     * @return void
     */
    protected function mergeGroupOptions()
    {
        $parentOption = $this->parent->getOption();
        // 合并分组参数
        foreach ($this->mergeOptions as $item) {
            if (isset($parentOption[$item]) && isset($this->option[$item])) {
                $this->option[$item] = array_merge($parentOption[$item], $this->option[$item]);
            }
        }

        $this->option = array_merge($parentOption, $this->option);
    }

    /**
     * 路由绑定模型实例
     * @access protected
     * @param  array|\Clousre    $bindModel 绑定模型
     * @param  array             $matches   路由变量
     * @return void
     */
    protected function createBindModel($bindModel, $matches)
    {
        foreach ($bindModel as $key => $val) {
            if ($val instanceof \Closure) {
                $result = Container::getInstance()->invokeFunction($val, $matches);
            } else {
                $fields = explode('&', $key);

                if (is_array($val)) {
                    list($model, $exception) = $val;
                } else {
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
                        $where[] = [$field, '=', $matches[$field]];
                    }
                }

                if ($match) {
                    $query  = strpos($model, '\\') ? $model::where($where) : Container::get('app')->model($model)->where($where);
                    $result = $query->failException($exception)->find();
                }
            }

            if (!empty($result)) {
                // 注入容器
                Container::getInstance()->instance(get_class($result), $result);
            }
        }
    }

    /**
     * 处理路由请求缓存
     * @access protected
     * @param  Request       $request 请求对象
     * @param  string|array  $cache  路由缓存
     * @return void
     */
    protected function parseRequestCache($request, $cache)
    {
        if (is_array($cache)) {
            list($key, $expire, $tag) = array_pad($cache, 3, null);
        } else {
            $key    = str_replace('|', '/', $request->url());
            $expire = $cache;
            $tag    = null;
        }

        $request->cache($key, $expire, $tag);
    }

    /**
     * 解析匹配到的规则路由
     * @access public
     * @param  Request   $request 请求对象
     * @param  string    $rule 路由规则
     * @param  string    $route 路由地址
     * @param  string    $url URL地址
     * @param  array     $option 路由参数
     * @param  array     $matches 匹配的变量
     * @return Dispatch
     */
    public function parseRule($request, $rule, $route, $url, $option = [], $matches = [])
    {
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
        if (isset($option['model'])) {
            $this->createBindModel($option['model'], $matches);
        }

        // 指定Header数据
        if (!empty($option['header'])) {
            $header = $option['header'];
            Container::get('hook')->add('response_send', function ($response) use ($header) {
                $response->header($header);
            });
        }

        // 指定Response响应数据
        if (!empty($option['response'])) {
            Container::get('hook')->add('response_send', $option['response']);
        }

        // 开启请求缓存
        if (isset($option['cache']) && $request->isGet()) {
            $this->parseRequestCache($request, $option['cache']);
        }

        // 解析额外参数
        $count = substr_count($rule, '/');
        $url   = array_slice(explode('|', $url), $count + 1);
        $this->parseUrlParams(implode('|', $url), $matches);

        // 记录匹配的路由信息
        $request->routeInfo(['rule' => $rule, 'route' => $route, 'option' => $option, 'var' => $matches]);

        // 检测路由after行为
        if (!empty($option['after'])) {
            $dispatch = $this->checkAfter($option['after']);

            if (false !== $dispatch) {
                return $dispatch;
            }
        }

        // 数据自动验证
        if (isset($option['validate'])) {
            $this->autoValidate($option['validate'], $request);
        }

        // 发起路由调度
        return $this->dispatch($request, $route, $option);
    }

    /**
     * 验证数据
     * @access protected
     * @param  array             $option
     * @param  \think\Request    $request
     * @return void
     * @throws ValidateException
     */
    protected function autoValidate($option, $request)
    {
        list($validate, $scene, $message, $batch) = $option;

        if (is_array($validate)) {
            // 指定验证规则
            $v = Container::get('app')->validate();
            $v->rule($validate);
        } else {
            // 调用验证器
            $v = Container::get('app')->validate($validate);
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        if (!empty($message)) {
            $v->message($message);
        }

        // 批量验证
        if ($batch) {
            $v->batch(true);
        }

        if (!$v->check($request->param())) {
            throw new ValidateException($v->getError());
        }
    }

    /**
     * 检查路由前置行为
     * @access protected
     * @param  mixed   $before 前置行为
     * @return mixed
     */
    protected function checkBefore($before)
    {
        $hook = Container::get('hook');

        foreach ((array) $before as $behavior) {
            $result = $hook->exec($behavior);

            if (false === $result) {
                return false;
            }
        }
    }

    /**
     * 检查路由后置行为
     * @access protected
     * @param  mixed   $after 后置行为
     * @return mixed
     */
    protected function checkAfter($after)
    {
        $hook = Container::get('hook');

        $result = null;

        foreach ((array) $after as $behavior) {
            $result = $hook->exec($behavior);

            if (!is_null($result)) {
                break;
            }
        }

        // 路由规则重定向
        if ($result instanceof Response) {
            return new ResponseDispatch($result);
        } elseif ($result instanceof Dispatch) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 发起路由调度
     * @access protected
     * @param  Request   $request Request对象
     * @param  mixed     $route  路由地址
     * @param  array     $option 路由参数
     * @return Dispatch
     */
    protected function dispatch($request, $route, $option)
    {
        if ($route instanceof \Closure) {
            // 执行闭包
            $result = new CallbackDispatch($route);
        } elseif ($route instanceof Response) {
            $result = new ResponseDispatch($route);
        } elseif (isset($option['view']) && false !== $option['view']) {
            $result = new ViewDispatch($route, is_array($option['view']) ? $option['view'] : []);
        } elseif (!empty($option['redirect']) || 0 === strpos($route, '/') || strpos($route, '://')) {
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
            $app = Container::get('app');
            $request->controller($route ? array_pop($route) : $app->config('default_controller'));
            $request->module($route ? array_pop($route) : $app->config('default_module'));
            $app->setModulePath($app->getAppPath() . ($app->config('app_multi_module') ? $request->module() . DIRECTORY_SEPARATOR : ''));
        } else {
            // 路由到模块/控制器/操作
            $result = $this->parseModule($route);
        }

        return $result;
    }

    /**
     * 解析URL地址为 模块/控制器/操作
     * @access protected
     * @param  string    $url URL地址
     * @return array
     */
    protected function parseModule($url)
    {
        list($path, $var) = $this->parseUrlPath($url);
        $config           = Container::get('config');
        $request          = Container::get('request');
        $action           = array_pop($path);
        $controller       = !empty($path) ? array_pop($path) : null;
        $module           = $config->get('app_multi_module') && !empty($path) ? array_pop($path) : null;
        $method           = $request->method();

        if ($config->get('use_action_prefix') && $this->router->getMethodPrefix($method)) {
            $prefix = $this->router->getMethodPrefix($method);
            // 操作方法前缀支持
            $action = 0 !== strpos($action, $prefix) ? $prefix . $action : $action;
        }

        // 设置当前请求的路由变量
        $request->route($var);

        // 路由到模块/控制器/操作
        return (new ModuleDispatch([$module, $controller, $action]))->convert(false);
    }

    /**
     * 路由检查
     * @access protected
     * @param  array     $option 路由参数
     * @param  Request   $request Request对象
     * @return bool
     */
    protected function checkOption($option, Request $request)
    {
        // 请求类型检测
        if (!empty($option['method'])) {
            if (is_string($option['method']) && false === stripos($option['method'], $request->method())) {
                return false;
            }
        }

        // AJAX PJAX 请求检查
        foreach (['ajax', 'pjax'] as $item) {
            if (isset($option[$item])) {
                $call = 'is' . $item;
                if ($option[$item] && !$request->$call() || !$option[$item] && $request->$call()) {
                    return false;
                }
            }
        }

        // 伪静态后缀检测
        if ($request->url() != '/' && ((isset($option['ext']) && false === stripos('|' . $option['ext'] . '|', '|' . $request->ext() . '|'))
            || (isset($option['deny_ext']) && false !== stripos('|' . $option['deny_ext'] . '|', '|' . $request->ext() . '|')))) {
            return false;
        }

        // 域名检查
        if ((isset($option['domain']) && !in_array($option['domain'], [$_SERVER['HTTP_HOST'], $request->subDomain()]))) {
            return false;
        }

        // HTTPS检查
        if ((isset($option['https']) && $option['https'] && !$request->isSsl())
            || (isset($option['https']) && !$option['https'] && $request->isSsl())) {
            return false;
        }

        return true;
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access protected
     * @param  string    $rule 路由规则
     * @param  array     $var 变量
     * @return void
     */
    protected function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if (Container::get('config')->get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }

        // 设置当前请求的参数
        Container::get('request')->route($var);
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access protected
     * @param  string    $url URL地址
     * @return array
     */
    protected function parseUrlPath($url)
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
     * 设置路由参数
     * @access public
     * @param  string    $method     方法名
     * @param  array     $args       调用参数
     * @return $this
     */
    public function __call($method, $args)
    {
        if (count($args) > 1) {
            $args[0] = $args;
        }
        array_unshift($args, $method);

        return call_user_func_array([$this, 'option'], $args);
    }
}
