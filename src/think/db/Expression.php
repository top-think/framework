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
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  string    $field
     * @param  integer   $bindType
     * @return string
     */
    abstract public function parse(Query $query, string $key, string $exp, string $field, int $bindType): string;

}
