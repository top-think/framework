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
use think\Debug;
use think\Log;

class Hook
{

    private static $tags = [];

    /**
     * 动态添加行为扩展到某个标签
     * @param string    $tag 标签名称
     * @param mixed     $behavior 行为名称
     * @param bool      $first 是否放到开头执行
     * @return void
     */
    public static function add($tag, $behavior, $first = false)
    {
        if (!isset(self::$tags[$tag])) {
            self::$tags[$tag] = [];
        }
        if (is_array($behavior)) {
            self::$tags[$tag] = array_merge(self::$tags[$tag], $behavior);
        } elseif ($first) {
            array_unshift(self::$tags[$tag], $behavior);
        } else {
            self::$tags[$tag][] = $behavior;
        }
    }

    /**
     * 批量导入插件
     * @param array    $tags 插件信息
     * @param boolean $recursive 是否递归合并
     */
    public static function import($tags, $recursive = true)
    {
        empty($tags) && $tags = [];
        if (!$recursive) {
            // 覆盖导入
            self::$tags = array_merge(self::$tags, $tags);
        } else {
            // 合并导入
            foreach ($tags as $tag => $val) {
                if (!isset(self::$tags[$tag])) {
                    self::$tags[$tag] = [];
                }

                if (!empty($val['_overlay'])) {
                    // 可以针对某个标签指定覆盖模式
                    unset($val['_overlay']);
                    self::$tags[$tag] = $val;
                } else {
                    // 合并模式
                    self::$tags[$tag] = array_merge(self::$tags[$tag], $val);
                }
            }
        }
    }

    /**
     * 获取插件信息
     * @param string $tag 插件位置 留空获取全部
     * @return array
     */
    public static function get($tag = '')
    {
        if (empty($tag)) {
            // 获取全部的插件信息
            return self::$tags;
        } else {
            return self::$tags[$tag];
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
    public static function listen($tag, &$params = null, $extra = null, $once = false)
    {
        $results = [];
        if (isset(self::$tags[$tag])) {
            foreach (self::$tags[$tag] as $name) {

                if (App::$debug) {
                    Debug::remark('behavior_start', 'time');
                }

                $result = self::exec($name, $tag, $params, $extra);

                if (!is_null($result) && $once) {
                    return $result;
                }

                if (App::$debug) {
                    Debug::remark('behavior_end', 'time');
                    if ($name instanceof \Closure) {
                        $name = 'Closure';
                    } elseif (is_object($name)) {
                        $name = get_class($name);
                    }
                    Log::record('[ BEHAVIOR ] Run ' . $name . ' @' . $tag . ' [ RunTime:' . Debug::getRangeTime('behavior_start', 'behavior_end') . 's ]', 'info');
                }
                if (false === $result) {
                    // 如果返回false 则中断行为执行
                    break;
                }
                $results[] = $result;
            }
        }
        return $once ? null : $results;
    }

    /**
     * 执行某个行为
     * @param mixed     $class 要执行的行为
     * @param string    $tag 方法名（标签名）
     * @param Mixed     $params 传人的参数
     * @param mixed     $extra 额外参数
     * @return mixed
     */
    public static function exec($class, $tag = '', &$params = null,$extra=null)
    {
        if ($class instanceof \Closure) {
            $result = call_user_func_array($class, [ & $params,$extra]);
        } elseif (is_object($class)) {
            $result = call_user_func_array([$class, $tag], [ & $params,$extra]);
        } else {
            $obj    = new $class();
            $result = ($tag && is_callable([$obj, $tag])) ? $obj->$tag($params,$extra) : $obj->run($params,$extra);
        }
        return $result;
    }
}
