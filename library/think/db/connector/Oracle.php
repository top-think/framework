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
use think\Db;
use think\db\Connection;

/**
 * Oracle数据库驱动
 */
class Oracle extends Connection
{

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        $dsn = 'oci:dbname=';
        if (!empty($config['hostname'])) {
            //  Oracle Instant Client
            $dsn .= '//' . $config['hostname'] . ($config['hostport'] ? ':' . $config['hostport'] : '') . '/';
        }
        $dsn .= $config['database'];
        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }
        return $dsn;
    }

    /**
     * 执行语句
     * @access public
     * @param string $sql  sql指令
     * @param array $bind 参数绑定
     * @param boolean $getLastInsID 是否获取自增ID
     * @param string $sequence 序列名
     * @return integer
     * @throws \Exception
     * @throws \think\Exception
     */
    public function execute($sql, $bind = [], $getLastInsID = false, $sequence = null)
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }

        // 根据参数绑定组装最终的SQL语句
        $this->queryStr = $this->getRealSql($sql, $bind);

        $flag = false;
        if (preg_match("/^\s*(INSERT\s+INTO)\s+(\w+)\s+/i", $sql, $match)) {
            if (is_null($sequence)) {
                $sequence = Config::get("db_sequence_prefix") . str_ireplace(Config::get("database.prefix"), "", $match[2]);
            }
            $flag = (boolean) $this->query("SELECT * FROM all_sequences WHERE sequence_name='" . strtoupper($sequence) . "'");
        }

        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }

        Db::$executeTimes++;
        try {
            // 记录开始执行时间
            $this->debug(true);
            $this->PDOStatement = $this->linkID->prepare($sql);
            // 参数绑定操作
            $this->bindValue($bind);
            $result = $this->PDOStatement->execute();
            $this->debug(false);
            $this->numRows = $this->PDOStatement->rowCount();
            if ($flag || preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $sql)) {
                $this->lastInsID = $this->linkID->lastInsertId();
                if ($getLastInsID) {
                    return $this->lastInsID;
                }
            }
            return $this->numRows;
        } catch (\PDOException $e) {
            throw new \think\Exception($this->getError());
        }
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
        $sql             = "select a.column_name,data_type,DECODE (nullable, 'Y', 0, 1) notnull,data_default, DECODE (A .column_name,b.column_name,1,0) pk from all_tab_columns a,(select column_name from all_constraints c, all_cons_columns col where c.constraint_name = col.constraint_name and c.constraint_type = 'P' and c.table_name = '" . strtoupper($tableName) . "' ) b where table_name = '" . strtoupper($tableName) . "' and a.column_name = b.column_name (+)";
        $pdo             = $this->linkID->query($sql);
        $result          = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info            = [];
        if ($result) {
            foreach ($result as $key => $val) {
                $val                       = array_change_key_case($val);
                $info[$val['column_name']] = [
                    'name'    => $val['column_name'],
                    'type'    => $val['data_type'],
                    'notnull' => $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'],
                    'autoinc' => $val['pk'],
                ];
            }
        }
        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access   public
     * @param string $dbName
     * @return array
     */
    public function getTables($dbName = '')
    {
        $pdo    = $this->linkID->query("select table_name from all_tables");
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

    protected function supportSavepoint()
    {
        return true;
    }
}
