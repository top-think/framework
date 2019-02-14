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

namespace think\db;

abstract class Expression
{
    /**
     * 查询表达式
     *
     * @var mixed
     */
    protected $value;

    /**
     * 创建一个查询表达式
     *
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 分析查询表达式
     *
     * @param  Query     $query         查询对象
     * @param  string    $key           查询字段（处理）
     * @param  string    $exp           查询表达式
     * @param  string    $field         查询字段（原始）
     * @param  integer   $bindType      字段绑定类型
     * @return string
     */
    abstract public function parse(Query $query, string $key, string $exp, string $field, int $bindType): string;

}
