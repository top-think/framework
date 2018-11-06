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

namespace think\response;

use think\Container;
use think\Response;

class View extends Response
{
    // 输出参数
    protected $options = [];
    protected $vars    = [];
    protected $filter;
    protected $contentType = 'text/html';

    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return string
     */
    protected function output($data): string
    {
        // 渲染模板输出
        $config = Container::get('config');
        return Container::get('view')
            ->init($config->pull('template'))
            ->filter($this->filter)
            ->fetch($data, $this->vars);
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
     * @param  mixed $name  变量名
     * @param  mixed $value 变量值
     * @return $this
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->vars = array_merge($this->vars, $name);
        } else {
            $this->vars[$name] = $value;
        }

        return $this;
    }

    /**
     * 视图内容过滤
     * @access public
     * @param callable $filter
     * @return $this
     */
    public function filter(?callable $filter)
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
    public function exists($name)
    {
        return Container::get('view')
            ->init(Container::get('config')->pull('template'))
            ->exists($name);
    }

}
