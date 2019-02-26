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

namespace think\route;

use think\Container;
use think\Request;
use think\Response;
use think\Validate;

abstract class Dispatch
{
    /**
     * 应用对象
     * @var \think\App
     */
    protected $app;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 路由规则
     * @var Rule
     */
    protected $rule;

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
     * @var int
     */
    protected $code;

    /**
     * 是否进行大小写转换
     * @var bool
     */
    protected $convert;

    public function __construct(Request $request, Rule $rule, $dispatch, array $param = [], int $code = null)
    {
        $this->request  = $request;
        $this->rule     = $rule;
        $this->app      = Container::pull('app');
        $this->dispatch = $dispatch;
        $this->param    = $param;
        $this->code     = $code;

        if (isset($param['convert'])) {
            $this->convert = $param['convert'];
        }
    }

    public function init()
    {
        // 执行路由后置操作
        if ($this->rule->doAfter()) {
            // 设置请求的路由信息
            $this->request->setRoute($this->rule->getVars());

            // 设置当前请求的参数
            $this->request->routeInfo([
                'rule'   => $this->rule->getRule(),
                'route'  => $this->rule->getRoute(),
                'option' => $this->rule->getOption(),
                'var'    => $this->rule->getVars(),
            ]);

            $this->doRouteAfter();
        }

        return $this;
    }

    /**
     * 执行路由调度
     * @access public
     * @return mixed
     */
    public function run(): Response
    {
        $option = $this->rule->getOption();

        // 数据自动验证
        if (isset($option['validate'])) {
            $this->autoValidate($option['validate']);
        }

        $data = $this->exec();

        return $this->autoResponse($data);
    }

    protected function autoResponse($data): Response
    {
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $isAjax = $this->request->isAjax();
            $type   = $isAjax ? $this->rule->config('default_ajax_return') : $this->rule->config('default_return_type');

            $response = Response::create($data, $type);
        } else {
            $data = ob_get_clean();

            $content  = false === $data ? '' : $data;
            $status   = false === $data ? 204 : 200;
            $response = Response::create($content, '', $status);
        }

        return $response;
    }

    /**
     * 检查路由后置操作
     * @access protected
     * @return void
     */
    protected function doRouteAfter(): void
    {
        // 记录匹配的路由信息
        $option  = $this->rule->getOption();
        $matches = $this->rule->getVars();

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
            $this->app['event']->listen('ResponseSend', function ($response) use ($header) {
                $response->header($header);
            });
        }

        // 指定Response响应数据
        if (!empty($option['response'])) {
            foreach ($option['response'] as $response) {
                $this->app['event']->listen('ResponseSend', $response);
            }
        }

        // 开启请求缓存
        if (isset($option['cache']) && $this->request->isGet()) {
            $this->parseRequestCache($option['cache']);
        }

        if (!empty($option['append'])) {
            $this->request->setRoute($option['append']);
        }
    }

    /**
     * 路由绑定模型实例
     * @access protected
     * @param  array    $bindModel 绑定模型
     * @param  array    $matches   路由变量
     * @return void
     */
    protected function createBindModel(array $bindModel, array $matches): void
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
                    $result = $model::where($where)->failException($exception)->find();
                }
            }

            if (!empty($result)) {
                // 注入容器
                $this->app->instance(get_class($result), $result);
            }
        }
    }

    /**
     * 处理路由请求缓存
     * @access protected
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

        $this->request->cache($key, (int) $expire, $tag);
    }

    /**
     * 验证数据
     * @access protected
     * @param  array             $option
     * @return void
     * @throws \think\exception\ValidateException
     */
    protected function autoValidate(array $option): void
    {
        list($validate, $scene, $message, $batch) = $option;

        if (is_array($validate)) {
            // 指定验证规则
            $v = new Validate($validate, $message);
        } else {
            // 调用验证器
            $class = $this->app->parseClass('validate', $validate);
            $v     = $class::make([], $message);

            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->batch($batch)->failException(true)->check($this->request->param());
    }

    public function convert(bool $convert)
    {
        $this->convert = $convert;

        return $this;
    }

    public function getDispatch()
    {
        return $this->dispatch;
    }

    public function getParam(): array
    {
        return $this->param;
    }

    abstract public function exec();

    public function __sleep()
    {
        return ['rule', 'dispatch', 'convert', 'param', 'code', 'controller', 'actionName'];
    }

    public function __wakeup()
    {
        $this->app     = Container::pull('app');
        $this->request = $this->app->request;
    }

    public function __debugInfo()
    {
        return [
            'dispatch' => $this->dispatch,
            'param'    => $this->param,
            'code'     => $this->code,
        ];
    }
}
