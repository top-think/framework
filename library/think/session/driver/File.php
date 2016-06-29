<?php

namespace think\session\driver;

use SessionHandler;
use think\Exception;

class File extends SessionHandler
{

    protected $config = [
        'save_path' => '', //保存路径
        'suffix' => ''//文件后缀
    ];

    public function __construct($config = [])
    {
        defined('TEMP_PATH') && $this->config['save_path'] = TEMP_PATH;
        $this->config = array_merge($this->config, $config);
        empty($this->config['save_path']) || $this->config['save_path'] = rtrim($this->config['save_path'], '\\/');
    }

    /**
     * 创建session_id
     * @return string
     */
    public function create_sid()
    {
        return md5(implode($_SERVER));
    }

    /**
     * 打开Session
     * @access public
     * @param string $save_path
     * @param mixed  $session_name
     * @return bool
     * @throws Exception
     */
    public function open($save_path, $session_name)
    {
        empty($this->config['save_path']) && $this->config['save_path'] = rtrim($save_path, '\\/');
        empty($this->config['suffix']) && $this->config['suffix'] = strtolower($session_name);
        try {
            is_dir($this->config['save_path']) || mkdir($this->config['save_path'], 0666, true);
        } catch (\Exception $e) {
            throw new Exception('directory creation failed');
        }
        return true;
    }

    /**
     * 读取Session
     * @access public
     * @param string $session_id
     * @return string
     */
    public function read($session_id)
    {
        $file = $this->getFilePath($session_id);
        return is_file($file) ? file_get_contents($file) : '';
    }

    /**
     * 写入Session
     * @param string $session_id
     * @param string $session_data
     * @return bool
     * @throws Exception
     */
    public function write($session_id, $session_data)
    {
        try {
            return file_put_contents($this->getFilePath($session_id), $session_data, LOCK_EX) !== false;
        } catch (\Exception $e) {
            throw new Exception('cache write error');
        }
    }

    /**
     * 删除Session
     * @access public
     * @param string $session_id
     * @return bool
     */
    public function destroy($session_id)
    {
        $file = $this->getFilePath($session_id);
        return is_file($file) ? unlink($file) : true;
    }

    /**
     * 关闭Session
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param int $sessMaxLifeTime
     * @return bool
     */
    public function gc($sessMaxLifeTime)
    {
        array_map(function($v) use ($sessMaxLifeTime) {
            $lifeTime = $_SERVER['REQUEST_TIME'] - fileatime($v);
            if ($lifeTime > $sessMaxLifeTime && is_file($v)) {
                return unlink($v);
            }
            return true;
        }, glob($this->config['save_path'] . DIRECTORY_SEPARATOR . '*.' . $this->config['suffix'], GLOB_NOSORT));
        return true;
    }

    /**
     * 获取文件路径
     * @param string $session_id
     * @return string
     */
    protected function getFilePath($session_id)
    {
        return $this->config['save_path'] . DIRECTORY_SEPARATOR . $session_id . '.' . $this->config['suffix'];
    }

}
