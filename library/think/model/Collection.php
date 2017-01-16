<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\model;

use think\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * 延迟预载入关联查询
     * @access public
     * @param mixed $relation 关联
     * @return $this
     */
    public function load($relation)
    {
        $item = current($this->items);
        $item->eagerlyResultSet($this->items, $relation);
        return $this;
    }
}
