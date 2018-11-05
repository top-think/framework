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
declare (strict_types = 1);

namespace think\db;

class Fetch
{
    /**
     * 查询对象
     * @var Query
     */
    protected $query;

    /**
     * 创建一个查询SQL获取对象
     *
     * @param  Query    $query      查询对象
     */
    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    public function __call($method, $args)
    {
        if (in_array(strtolower($method), ['find', 'select', 'insert', 'selectinsert', 'update', 'delete', 'value', 'column'])) {
            $fun = 'fetch' . $method;
            return call_user_func_array([$this->query, $fun], $args);
        }

        $result = call_user_func_array([$this->query, $method], $args);
        return $result === $this->query ? $this : $result;
    }
}
