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

namespace think\db\builder;

use think\Db;
use think\db\Builder;

/**
 * Oracle数据库驱动
 */
class Oracle extends Builder
{

    protected $selectSql = 'SELECT * FROM (SELECT thinkphp.*, rownum AS numrow FROM (SELECT  %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%) thinkphp ) %LIMIT%%COMMENT%';

    /**
     * limit
     * @access public
     * @return string
     */
    public function parseLimit($limit)
    {
        $limitStr = '';
        if (!empty($limit)) {
            $limit = explode(',', $limit);
            if (count($limit) > 1) {
                $limitStr = "(numrow>" . $limit[0] . ") AND (numrow<=" . ($limit[0] + $limit[1]) . ")";
            } else {
                $limitStr = "(numrow>0 AND numrow<=" . $limit[0] . ")";
            }

        }
        return $limitStr ? ' WHERE ' . $limitStr : '';
    }

    /**
     * 设置锁机制
     * @access protected
     * @param bool|false $lock
     * @return string
     */
    protected function parseLock($lock = false)
    {
        if (!$lock) {
            return '';
        }

        return ' FOR UPDATE NOWAIT ';
    }

    /**
     * 字段和表名处理
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey($key)
    {
        $key = trim($key);
        if (strpos($key, '$.') && false === strpos($key, '(')) {
            // JSON字段支持
            list($field, $name) = explode($key, '$.');
            $key                = $field . '."' . $name . '"';
        }
        return $key;
    }

    /**
     * 随机排序
     * @access protected
     * @return string
     */
    protected function parseRand()
    {
        return 'DBMS_RANDOM.value';
    }

}
