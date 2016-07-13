<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: weianguo <366958903@qq.com>
// +----------------------------------------------------------------------

namespace think\db\connector;

use PDO;
use think\db\Connection;
use think\Log;
use think\Db;
use think\Exception;
use think\exception\PDOException;

/**
 * firebird数据库驱动
 */
class Firebird extends Connection
{

    /**
     * 解析pdo连接的dsn信息
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
       $dsn = 'firebird:dbname=' . $config['hostname'].'/'.$config['hostport'].':'.$config['database'];
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
        $sql= 'SELECT TRIM(RF.RDB$FIELD_NAME) AS FIELD,RF.RDB$DEFAULT_VALUE AS DEFAULT1,RF.RDB$NULL_FLAG AS NULL1,TRIM(T.RDB$TYPE_NAME) || \'(\' || F.RDB$FIELD_LENGTH || \')\' as TYPE FROM RDB$RELATION_FIELDS RF LEFT JOIN RDB$FIELDS F ON (F.RDB$FIELD_NAME = RF.RDB$FIELD_SOURCE) LEFT JOIN RDB$TYPES T ON (T.RDB$TYPE = F.RDB$FIELD_TYPE) WHERE RDB$RELATION_NAME=UPPER(\'' . $tableName . '\') AND T.RDB$FIELD_NAME = \'RDB$FIELD_TYPE\' ORDER By RDB$FIELD_POSITION';
  		$result = $this->linkID->query($sql);
        $info   = [];
        if ($result) {
            foreach ($result as $key => $val) {
                $info[$val[0]] = array(
                    'name'    => $val[0],
                    'type'    => $val[3],
                    'notnull' => ($val[2]==1),
                    'default' => $val[1],
                    'primary' => false,
                    'autoinc' => false,
                );
            }
        }
		//获取主键
        $sql     = 'select TRIM(b.rdb$field_name) as field_name from rdb$relation_constraints a join rdb$index_segments b on a.rdb$index_name=b.rdb$index_name where a.rdb$constraint_type=\'PRIMARY KEY\' and a.rdb$relation_name=UPPER(\'' . $tableName . '\')';
        $rs_temp = $this->linkID->query($sql);
        foreach ($rs_temp as $row) {
            $info[$row[0]]['primary'] = true;
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
        $sql    = 'SELECT DISTINCT RDB$RELATION_NAME FROM RDB$RELATION_FIELDS WHERE RDB$SYSTEM_FLAG=0';
        $result    = $this->query($sql);
        $info   = [];
        foreach ($result as $key => $val) {
            $info[$key] = trim(current($val));
        }
        return $info;
    }
	
	/**
     * 执行语句
     * @access public
     * @param string        $sql sql指令
     * @param array         $bind 参数绑定
     * @param boolean       $getLastInsID 是否获取自增ID
     * @param string        $sequence 自增序列名
     * @return int
     * @throws BindParamException
     * @throws PDOException
     */
    public function execute($sql, $bind = [], $getLastInsID = false, $sequence = null)
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }
        // 根据参数绑定组装最终的SQL语句
        $this->queryStr = $this->getRealSql($sql, $bind);

        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
		
		$bind=array_map(function($v){
		return array_map(function($v2){
			return mb_convert_encoding($v2,'gbk','utf-8');},$v);
		},$bind);
		
        Db::$executeTimes++;
        try {
            // 调试开始
            $this->debug(true);
            // 预处理
            $this->PDOStatement = $this->linkID->prepare(mb_convert_encoding($sql,'gbk','utf-8'));
            // 参数绑定操作
            $this->bindValue($bind);
            // 执行语句
            $result = $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);

            $this->numRows = $this->PDOStatement->rowCount();
            return $this->numRows;
        } catch (\PDOException $e) {
            throw new PDOException($e, $this->config, $this->queryStr);
        }
    }
	
	/**
     * 启动事务
     * @access public
     * @return bool|null
     */
    public function startTrans()
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }

        ++$this->transTimes;

        if (1 == $this->transTimes) {
			$this->linkID->setAttribute(\PDO::ATTR_AUTOCOMMIT,false);
            $this->linkID->beginTransaction();
        } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
            $this->linkID->exec(
                $this->parseSavepoint('trans' . $this->transTimes)
            );
        }
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
