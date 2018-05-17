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
use think\route\dispatch\ResponseDispatch;

abstract class Dispatch
{
    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 路由规则
     * @var RuleItem
     */
    protected $router;

    /**
     * 调度信息
     * @var mixed
     */
    protected $dispatch;

    /**
     * 调度参数
     * @var array
     */
    protected $param;

    /**
     * 状态码
     * @var string
     */
    protected $code;

    /**
     * 是否进行大小写转换
     * @var bool
     */
    protected $convert;

    public function __construct(Request $request, RuleItem $router, $dispatch, $param = [], $code = null)
    {
        $this->request  = $request;
        $this->router   = $router;
        $this->app      = Container::get('app');
        $this->dispatch = $dispatch;
        $this->param    = $param;
        $this->code     = $code;

        if (isset($param['convert'])) {
            $this->convert = $param['convert'];
        }

        // 设置请求的路由信息
        $this->request->routeInfo([
            'rule'   => $this->router->getRule(),
            'route'  => $this->router->getRoute(),
            'option' => $this->router->getOption(),
            'var'    => $this->router->getVars(),
        ]);

        // 初始化
        $this->init();
    }

    protected function init()
    {}

    /**
     * 执行路由调度
     * @access public
     * @return mixed
     */
    public function run()
    {
        $result = $this->routeAfter();

        if ($result instanceof Response) {
            return $result;
        }

        return $this->exec();
    }

    /**
     * 检查路由后置操作
     * @access protected
     * @return mixed
     */
    protected function routeAfter()
    {
        // 记录匹配的路由信息
        $option  = $this->router->getOption();
        $matches = $this->router->getVars();

        // 添加中间件
        if (!empty($option['middleware'])) {
            $this->app['middleware']->import($option['middleware']);
        }

        // 绑定模型数据
        if (!empty($option['model'])) {
            $this->createBindModel($option['model'], $matches);
        }

        // 指定Header数据
        if (!empty($option['header'])) {
            $header = $option['header'];
            $this->app['hook']->add('response_send', function ($response) use ($header) {
                $response->header($header);
            });
        }

        // 指定Response响应数据
        if (!empty($option['response'])) {
            $this->app['hook']->add('response_send', $option['response']);
        }

        // 开启请求缓存
        if (isset($option['cache']) && $request->isGet()) {
            $this->parseRequestCache($option['cache']);
        }

        if (!empty($option['append'])) {
            $this->request->route($option['append']);
        }

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
    }

    /**
     * 检查路由后置行为
     * @access protected
     * @param  mixed   $after 后置行为
     * @return mixed
     */
    protected function checkAfter($after)
    {
        $this->app['log']->notice('路由后置行为建议使用中间件替代！');

        $hook = $this->app['hook'];

        $result = null;

        foreach ((array) $after as $behavior) {
            $result = $hook->exec($behavior);

            if (!is_null($result)) {
                break;
            }
        }

        // 路由规则重定向
        if ($result instanceof Response) {
            return new ResponseDispatch($result, $this->router);
        }

        return false;
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
            $v = $this->app->validate();
            $v->rule($validate);
        } else {
            // 调用验证器
            $v = $this->app->validate($validate);
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
     * 处理路由请求缓存
     * @access protected
     * @param  Request       $request 请求对象
     * @param  string|array  $cache  路由缓存
     * @return void
     */
    protected function parseRequestCache($cache)
    {
        if (is_array($cache)) {
            list($key, $expire, $tag) = array_pad($cache, 3, null);
        } else {
            $key    = str_replace('|', '/', $this->request->url());
            $expire = $cache;
            $tag    = null;
        }

        $this->request->cache($key, $expire, $tag);
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
                $result = $this->app->invokeFunction($val, $matches);
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
                    $query  = strpos($model, '\\') ? $model::where($where) : $this->app->model($model)->where($where);
                    $result = $query->failException($exception)->find();
                }
            }

            if (!empty($result)) {
                // 注入容器
                $this->app->instance(get_class($result), $result);
            }
        }
    }

    public function convert($convert)
    {
        $this->convert = $convert;

        return $this;
    }

    public function getDispatch()
    {
        return $this->dispatch;
    }

    public function getParam()
    {
        return $this->param;
    }

    abstract public function exec();

}
