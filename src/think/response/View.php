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

namespace think\response;

use think\Response;
use think\View as BaseView;

/**
 * View Response
 */
class View extends Response
{
    // 输出参数
    protected $options = [];
    protected $vars    = [];
    protected $filter;
    protected $contentType = 'text/html';
    protected $view;

    public function __construct(BaseView $view, $data = '', int $code = 200)
    {
        parent::__construct($data, $code);
        $this->view = $view;
    }

    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return string
     */
    protected function output($data): string
    {
        // 渲染模板输出
        return $this->view->filter($this->filter)
            ->assign($this->vars)
            ->fetch($data);
    }

    /**
     * 获取视图变量
     * @access public
     * @param  string $name 模板变量
     * @return mixed
     */
    public function getVars(string $name = null)
    {
        if (is_null($name)) {
            return $this->vars;
        } else {
            return $this->vars[$name] ?? null;
        }
    }

    /**
     * 模板变量赋值
     * @access public
     * @param  array $vars  变量
     * @return $this
     */
    public function assign(array $vars)
    {
        $this->vars = array_merge($this->vars, $vars);

        return $this;
    }

    /**
     * 视图内容过滤
     * @access public
     * @param callable $filter
     * @return $this
     */
    public function filter(callable $filter = null)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * 检查模板是否存在
     * @access public
     * @param  string  $name 模板名
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->view->exists($name);
    }

}
