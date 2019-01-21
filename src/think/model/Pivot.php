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

namespace think\model;

use think\Model;

class Pivot extends Model
{

    /** @var Model */
    public $parent;

    protected $autoWriteTimestamp = false;

    /**
     * 架构函数
     * @access public
     * @param  array    $data 数据
     * @param  Model    $parent 上级模型
     * @param  string   $table 中间数据表名
     */
    public function __construct(array $data = [], $parent = null, string $table = '')
    {
        $this->parent = $parent;

        if (is_null($this->name)) {
            $this->name = $table;
        }

        parent::__construct($data);
    }

}
