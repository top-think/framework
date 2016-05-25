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

namespace think\db\connector;

use PDO;
use think\db\Connection;

/**
 * Pgsql数据库驱动
 */
class Pgsql extends Connection
{

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        $dsn = 'pgsql:dbname=' . $config['database'] . ';host=' . $config['hostname'];
        if (!empty($config['hostport'])) {
            $dsn .= ';port=' . $config['hostport'];
        }
        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    public function getFields($tableName)
    {
        $this->initConnect(true);
        list($tableName) = explode(' ', $tableName);
        $sql             = 'select fields_name as "field",fields_type as "type",fields_not_null as "null",fields_key_name as "key",fields_default as "default",fields_default as "extra" from table_msg(\'' . $tableName . '\');';
        $pdo             = $this->linkID->query($sql);
        $result          = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info            = [];
        if ($result) {
            foreach ($result as $key => $val) {
                $val                 = array_change_key_case($val);
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' === $val['null']), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                ];
            }
        }
        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    public function getTables($dbName = '')
    {
        $sql    = "select tablename as Tables_in_test from pg_tables where  schemaname ='public'";
        $pdo    = $this->linkID->query($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * SQL性能分析
     * @access protected
     * @param string $sql
     * @return array
     */
    protected function getExplain($sql)
    {
        return [];
    }
}
