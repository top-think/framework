<?php
/**
 * Copyright (c) 2017. hainuo<admin@hainuo.info>
 * update 20170125 增加数据库session驱动
 */
/**
 * 数据库方式Session驱动
 *    CREATE TABLE {prefix}_session (
 *      session_id varchar(255) NOT NULL,
 *      session_expire int(11) NOT NULL,
 *      session_data blob,
 *      UNIQUE KEY `session_id` (`session_id`)
 *    );
 *  配置文件session中增加(如果跟dabase是同一个配置，也请再填写一遍)
 * 'type' =>'mysqli',
 * 'db_prefix' => '',
 * 'hostname' => '',
 * 'password' => '',
 * 'username' => '',
 * 'hostport' => '',
 * 'session_db' =>'session',
 * 'database' => '',
 */
namespace think\session\driver;

use SessionHandler;

/**
 * Class Mysqli
 * @package think\session\driver
 */
class Mysqli extends SessionHandler
{

    /**
     * 数据库session驱动配置
     */
    protected $config = [
        'db_prefix'  => 'hn_',
        'session_db' => 'session',
        'hostname'   => 'localhost',
        'hostport'   => '3306',
        'username'   => '',
        'password'   => '',
        'life_time'  => 0,
    ];
    /**
     * 数据库句柄
     */
    protected $handler = '';
    protected $lifeTime = '';
    protected $sessionTable = '';

    /**
     * Db constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 打开Session
     * @access public
     * @param string $savePath
     * @param mixed $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName)
    {
        $this->lifeTime     = $this->config['life_time'] ?: ini_get('session.gc_maxlifetime');
        $this->sessionTable = $this->config['db_prefix'] . $this->config['session_db'];

        //从数据库链接
        $this->handler = mysqli_connect(
            $this->config['hostname'] . ':' . $this->config['hostport'],
            $this->config['username'], $this->config['password'],
            $this->config['database']
        );

        if (!$this->handler) {
            return false;
        }

        return true;
    }

    /**
     * 关闭Session
     * @access public
     */
    public function close()
    {
        $this->gc($this->lifeTime);
        return mysqli_close($this->handler);
    }

    /**
     * 读取Session
     * @access public
     * @param string $sessID
     * @return string
     */
    public function read($sessID)
    {
        $res = mysqli_query($this->handler, "SELECT session_data AS data FROM " . $this->sessionTable . " WHERE session_id = '$sessID'   AND session_expire >" . time());
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            if (empty($row))
                return '';
            return $row['data'];
        }
        return "";
    }

    /**
     * 写入Session
     * @access public
     * @param string $sessID
     * @param String $sessData
     * @return bool
     */
    public function write($sessID, $sessData)
    {
        $expire = time() + $this->lifeTime;
        mysqli_query($this->handler, "REPLACE INTO  " . $this->sessionTable . " (  session_id, session_expire, session_data)  VALUES( '$sessID', '$expire',  '$sessData')");
        if (mysqli_affected_rows($this->handler)) {
            return true;
        }

        return false;
    }

    /**
     * 删除Session
     * @access public
     * @param string $sessID
     * @return bool
     */
    public function destroy($sessID)
    {
        mysqli_query($this->handler, "DELETE FROM " . $this->sessionTable . " WHERE session_id = '$sessID'");
        if (mysqli_affected_rows($this->handler)) {
            return true;
        }

        return false;
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param string $sessMaxLifeTime
     * @return int
     */
    public function gc($sessMaxLifeTime)
    {
        mysqli_query($this->handler, "DELETE FROM " . $this->sessionTable . " WHERE session_expire < " . time());
        return mysqli_affected_rows($this->handler);
    }

}
