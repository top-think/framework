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

namespace think\session\driver;

use SessionHandlerInterface;
use think\Exception;

class Memcached implements SessionHandlerInterface
{
    protected $handler = null;
    protected $config  = [
        'host'     => '127.0.0.1', // memcache主机
        'port'     => 11211, // memcache端口
        'expire'   => 3600, // session有效期
        'timeout'  => 0, // 连接超时时间（单位：毫秒）
        'name'     => '', // session name （memcache key前缀）
        'username' => '', //账号
        'password' => '', //密码
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 打开Session
     * @access public
     * @param  string    $savePath
     * @param  mixed     $sessName
     * @return bool
     */
    public function open($savePath, $sessName): bool
    {
        // 检测php环境
        if (!extension_loaded('memcached')) {
            throw new Exception('not support:memcached');
        }

        $this->handler = new \Memcached;

        // 设置连接超时时间（单位：毫秒）
        if ($this->config['timeout'] > 0) {
            $this->handler->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->config['timeout']);
        }

        // 支持集群
        $hosts = explode(',', $this->config['host']);
        $ports = explode(',', $this->config['port']);

        if (empty($ports[0])) {
            $ports[0] = 11211;
        }

        // 建立连接
        $servers = [];
        foreach ((array) $hosts as $i => $host) {
            $servers[] = [$host, (isset($ports[$i]) ? $ports[$i] : $ports[0]), 1];
        }

        $this->handler->addServers($servers);

        if ('' != $this->config['username']) {
            $this->handler->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->handler->setSaslAuthData($this->config['username'], $this->config['password']);
        }

        return true;
    }

    /**
     * 关闭Session
     * @access public
     * @return bool
     */
    public function close(): bool
    {
        $this->gc(ini_get('session.gc_maxlifetime'));
        $this->handler->quit();
        $this->handler = null;

        return true;
    }

    /**
     * 读取Session
     * @access public
     * @param  string $sessID
     * @return string
     */
    public function read($sessID): string
    {
        return (string) $this->handler->get($this->config['name'] . $sessID);
    }

    /**
     * 写入Session
     * @access public
     * @param  string $sessID
     * @param  string $sessData
     * @return bool
     */
    public function write($sessID, $sessData): bool
    {
        return $this->handler->set($this->config['name'] . $sessID, $sessData, $this->config['expire']);
    }

    /**
     * 删除Session
     * @access public
     * @param  string $sessID
     * @return bool
     */
    public function destroy($sessID): bool
    {
        return $this->handler->delete($this->config['name'] . $sessID);
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param  string $sessMaxLifeTime
     * @return true
     */
    public function gc($sessMaxLifeTime): bool
    {
        return true;
    }
}
