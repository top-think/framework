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

namespace think\model\concern;

use think\db\Query;

/**
 * 查询范围
 */
trait Scope
{

    /**
     * 命名范围
     * @access public
     * @param string|array|\Closure $name 命名范围名称 逗号分隔
     * @internal  mixed                 ...$params 参数调用
     * @return Model|Query
     */
    public static function scope($name)
    {
        if ($name instanceof Query) {
            return $name;
        }

        $model     = new static();
        $params    = func_get_args();
        $params[0] = $model->db();

        if ($name instanceof \Closure) {
            call_user_func_array($name, $params);
        } elseif (is_string($name)) {
            $name = explode(',', $name);
        }

        if (is_array($name)) {
            foreach ($name as $scope) {
                $method = 'scope' . trim($scope);

                if (method_exists($model, $method)) {
                    call_user_func_array([$model, $method], $params);
                }
            }
        }

        return $model;
    }

    /**
     * 设置是否使用全局查询范围
     * @param bool $use 是否启用全局查询范围
     * @access public
     * @return Model
     */
    public static function useGlobalScope($use)
    {
        $model = new static();

        return $model;
    }

    public function __call($method, $args)
    {
        $query = $this->db(true, false);

        if (method_exists($this, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $query);
            call_user_func_array([$this, $method], $args);

            return $this;
        } else {
            return call_user_func_array([$query, $method], $args);
        }
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();
        $query = $model->db();

        if (method_exists($model, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $query);

            call_user_func_array([$model, $method], $args);
            return $query;
        } else {
            return call_user_func_array([$query, $method], $args);
        }
    }
}
