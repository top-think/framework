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

namespace think\db\builder;

use think\db\Builder;
use think\db\Query;

/**
 * mysql数据库驱动
 */
class Mysql extends Builder
{
    protected $updateSql = 'UPDATE %TABLE% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 字段和表名处理
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @return string
     */
    protected function parseKey(Query $query, $key)
    {
        $key = trim($key);

        if (strpos($key, '->') && false === strpos($key, '(')) {
            // JSON字段支持
            list($field, $name) = explode('->', $key);
            $key                = 'json_extract(' . $field . ', \'$.' . $name . '\')';
        } elseif (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]/', $key)) {
            list($table, $key) = explode('.', $key, 2);
            $alias             = $query->getOptions('alias');
            if (isset($alias[$table])) {
                $table = $alias[$table];
            } elseif ('__TABLE__' == $table) {
                $table = $query->getTable();
            }
        }

        if (!preg_match('/[,\'\"\*\(\)`.\s]/', $key)) {
            $key = '`' . $key . '`';
        }

        if (isset($table)) {
            if (strpos($table, '.')) {
                $table = str_replace('.', '`.`', $table);
            }

            $key = '`' . $table . '`.' . $key;
        }

        return $key;
    }

    /**
     * field分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $fields
     * @return string
     */
    protected function parseField(Query $query, $fields)
    {
        $fieldsStr = parent::parseField($query, $fields);
        $options   = $query->getOptions();

        if (!empty($options['point'])) {
            $array = [];
            foreach ($options['point'] as $key => $field) {
                $key     = !is_numeric($key) ? $key : $field;
                $array[] = 'AsText(' . $this->parseKey($query, $key) . ') AS ' . $this->parseKey($query, $field);
            }
            $fieldsStr .= ',' . implode(',', $array);
        }

        return $fieldsStr;
    }

    /**
     * 数组数据解析
     * @access protected
     * @param array  $data
     * @return mixed
     */
    protected function parseArrayData($data)
    {
        list($type, $value) = $data;

        switch (strtolower($type)) {
            case 'exp':
                $result = $value;
                break;
            case 'point':
                $fun   = isset($data[2]) ? $data[2] : 'GeomFromText';
                $point = isset($data[3]) ? $data[3] : 'POINT';
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }
                $result = $fun . '(\'' . $point . '(' . $value . ')\')';
                break;
            default:
                $result = false;
        }

        return $result;
    }

    /**
     * 随机排序
     * @access protected
     * @param Query     $query        查询对象
     * @return string
     */
    protected function parseRand(Query $query)
    {
        return 'rand()';
    }

}
