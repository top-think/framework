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

class Hook
{

    private $tags = [];

    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 动态添加行为扩展到某个标签
     * @param string    $tag 标签名称
     * @param mixed     $behavior 行为名称
     * @param bool      $first 是否放到开头执行
     * @return void
     */
    public function add($tag, $behavior, $first = false)
    {
        isset($this->tags[$tag]) || $this->tags[$tag] = [];

        if (is_array($behavior) && !is_callable($behavior)) {
            if (!array_key_exists('_overlay', $behavior) || !$behavior['_overlay']) {
                unset($behavior['_overlay']);
                $this->tags[$tag] = array_merge($this->tags[$tag], $behavior);
            } else {
                unset($behavior['_overlay']);
                $this->tags[$tag] = $behavior;
            }
        } elseif ($first) {
            array_unshift($this->tags[$tag], $behavior);
        } else {
            $this->tags[$tag][] = $behavior;
        }
    }

    /**
     * 批量导入插件
     * @param array        $tags 插件信息
     * @param boolean     $recursive 是否递归合并
     */
    public function import(array $tags, $recursive = true)
    {
        if ($recursive) {
            foreach ($tags as $tag => $behavior) {
                $this->add($tag, $behavior);
            }
        } else {
            $this->tags = $tags + $this->tags;
        }
    }

    /**
     * 获取插件信息
     * @param string $tag 插件位置 留空获取全部
     * @return array
     */
    public function get($tag = '')
    {
        if (empty($tag)) {
            //获取全部的插件信息
            return $this->tags;
        } else {
            return array_key_exists($tag, $this->tags) ? $this->tags[$tag] : [];
        }
    }

    /**
     * 监听标签的行为
     * @param string $tag    标签名称
     * @param mixed  $params 传入参数
     * @param mixed  $extra  额外参数
     * @param bool   $once   只获取一个有效返回值
     * @return mixed
     */
    public function listen($tag, $params = null, $extra = null, $once = false)
    {
        $results = [];
        $tags    = $this->get($tag);

        foreach ($tags as $key => $name) {
            $results[$key] = $this->exec($name, $tag, $params, $extra);
            if (false === $results[$key]) {
                // 如果返回false 则中断行为执行
                break;
            } elseif (!is_null($results[$key]) && $once) {
                break;
            }
        }

        return $once ? end($results) : $results;
    }

    /**
     * 执行某个行为
     * @param mixed     $class 要执行的行为
     * @param string    $tag 方法名（标签名）
     * @param Mixed     $params 传人的参数
     * @param mixed     $extra 额外参数
     * @return mixed
     */
    public function exec($class, $tag = '', $params = null, $extra = null)
    {
        $this->app->isDebug() && $this->app['debug']->remark('behavior_start', 'time');

        $method = Loader::parseName($tag, 1, false);

        if ($class instanceof \Closure) {
            $result = call_user_func_array($class, [ & $params, $extra]);
            $class  = 'Closure';
        } elseif (is_array($class)) {
            list($class, $method) = $class;

            $result = (new $class())->$method($params, $extra);
            $class  = $class . '->' . $method;
        } elseif (is_object($class)) {
            $result = $class->$method($params, $extra);
            $class  = get_class($class);
        } elseif (strpos($class, '::')) {
            $result = call_user_func_array($class, [ & $params, $extra]);
        } else {
            $obj    = new $class();
            $method = ($tag && is_callable([$obj, $method])) ? $method : 'run';
            $result = $obj->$method($params, $extra);
        }

        if ($this->app->isDebug()) {
            $debug = $this->app['debug'];
            $debug->remark('behavior_end', 'time');
            $this->app->log('[ BEHAVIOR ] Run ' . $class . ' @' . $tag . ' [ RunTime:' . $debug->getRangeTime('behavior_start', 'behavior_end') . 's ]');
        }

        return $result;
    }

}
