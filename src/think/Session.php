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

namespace think;

use think\exception\ClassNotFoundException;

class Session
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 是否初始化
     * @var bool
     */
    protected $init = null;

    /**
     * 锁驱动
     * @var object
     */
    protected $lockDriver = null;

    /**
     * 锁key
     * @var string
     */
    protected $sessKey = 'PHPSESSID';

    /**
     * 锁超时时间
     * @var integer
     */
    protected $lockTimeout = 3;

    /**
     * 是否启用锁机制
     * @var bool
     */
    protected $lock = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public static function __make(Config $config)
    {
        return new static($config->get('session', []));
    }

    /**
     * 配置
     * @access public
     * @param  array $config
     * @return void
     */
    public function setConfig(array $config = []): void
    {
        $this->config = array_merge($this->config, array_change_key_case($config));

        if (isset($config['use_lock'])) {
            $this->lock = $config['use_lock'];
        }
    }

    /**
     * session初始化
     * @access public
     * @param  array $config
     * @return void
     * @throws \think\Exception
     */
    public function init(array $config = []): void
    {
        $config = $config ?: $this->config;

        if (isset($config['use_lock'])) {
            $this->lock = $config['use_lock'];
        }

        if (isset($config['var_session_id']) && isset($_REQUEST[$config['var_session_id']])) {
            session_id($_REQUEST[$config['var_session_id']]);
        } elseif (!empty($config['id'])) {
            session_id($config['id']);
        }

        if (!empty($config['type'])) {
            // 读取session驱动
            $class = false !== strpos($config['type'], '\\') ? $config['type'] : '\\think\\session\\driver\\' . ucwords($config['type']);

            // 检查驱动类
            if (!class_exists($class) || !session_set_save_handler(new $class($config))) {
                throw new ClassNotFoundException('error session handler:' . $class, $class);
            }
        }

        if (!empty($config['auto_start'])) {
            try {
                session_start($config['options'] ?? []);
            } catch (\Exception $e) {
            }
            $this->init = true;
        } else {
            $this->init = false;
        }
    }

    /**
     * session自动启动或者初始化
     * @access public
     * @return void
     */
    public function boot(): void
    {
        if (is_null($this->init)) {
            $this->init();
        }

        if (false === $this->init) {
            if (PHP_SESSION_ACTIVE != session_status()) {
                session_start();
            }
            $this->init = true;
        }
    }

    /**
     * session设置
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @return void
     */
    public function set(string $name, $value): void
    {
        $this->lock();

        empty($this->init) && $this->boot();

        if (strpos($name, '.')) {
            // 二维数组赋值
            list($name1, $name2) = explode('.', $name);

            $_SESSION[$name1][$name2] = $value;
        } else {
            $_SESSION[$name] = $value;
        }

        $this->unlock();
    }

    /**
     * session获取
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $default 默认值
     * @return mixed
     */
    public function get(string $name = '', $default = null)
    {
        $this->lock();

        empty($this->init) && $this->boot();

        $value = $_SESSION;

        if ('' != $name) {
            $name = explode('.', $name);

            foreach ($name as $val) {
                if (isset($value[$val])) {
                    $value = $value[$val];
                } else {
                    $value = $default;
                    break;
                }
            }
        }

        $this->unlock();

        return $value;
    }

    /**
     * session 读写锁驱动实例化
     */
    protected function initDriver(): void
    {
        // 不在 init 方法中实例化lockDriver，是因为 init 方法不一定先于 set 或 get 方法调用
        $config = $this->config;

        if (!empty($config['type']) && !empty($config['use_lock'])) {
            // 读取session驱动
            $class = false !== strpos($config['type'], '\\') ? $config['type'] : '\\think\\session\\driver\\' . ucwords($config['type']);

            // 检查驱动类及类中是否存在 lock 和 unlock 函数
            if (class_exists($class) && method_exists($class, 'lock') && method_exists($class, 'unlock')) {
                $this->lockDriver = new $class($config);
            }
        }

        // 通过cookie获得session_id
        if (!empty($config['name'])) {
            $this->sessKey = $config['name'];
        }

        if (isset($config['lock_timeout']) && $config['lock_timeout'] > 0) {
            $this->lockTimeout = $config['lock_timeout'];
        }
    }

    /**
     * session 读写加锁
     * @access protected
     * @return void
     */
    protected function lock()
    {
        if (empty($this->lock)) {
            return;
        }

        $this->initDriver();

        if (null !== $this->lockDriver && method_exists($this->lockDriver, 'lock')) {
            $t = time();
            // 使用 session_id 作为互斥条件，即只对同一 session_id 的会话互斥。第一次请求没有 session_id
            $sessID = $_COOKIE[$this->sessKey] ?? '';

            do {
                if (time() - $t > $this->lockTimeout) {
                    $this->unlock();
                }
            } while (!$this->lockDriver->lock($sessID, $this->lockTimeout));
        }
    }

    /**
     * session 读写解锁
     * @access protected
     * @return void
     */
    protected function unlock()
    {
        if (empty($this->lock)) {
            return;
        }

        $this->pause();

        if ($this->lockDriver && method_exists($this->lockDriver, 'unlock')) {
            $sessID = $_COOKIE[$this->sessKey] ?? '';
            $this->lockDriver->unlock($sessID);
        }
    }

    /**
     * session获取并删除
     * @access public
     * @param  string        $name session名称
     * @return mixed
     */
    public function pull(string $name)
    {
        $result = $this->get($name);

        if ($result) {
            $this->delete($name);
            return $result;
        }
    }

    /**
     * session设置 下一次请求有效
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @return void
     */
    public function flash(string $name, $value): void
    {
        $this->set($name, $value);

        if (!$this->has('__flash__.__time__')) {
            $this->set('__flash__.__time__', $_SERVER['REQUEST_TIME_FLOAT']);
        }

        $this->push('__flash__', $name);
    }

    /**
     * 清空当前请求的session数据
     * @access public
     * @return void
     */
    public function flush()
    {
        if (!$this->init) {
            return;
        }

        $item = $this->get('__flash__');

        if (!empty($item)) {
            $time = $item['__time__'];

            if ($_SERVER['REQUEST_TIME_FLOAT'] > $time) {
                unset($item['__time__']);
                $this->delete($item);
                $this->set('__flash__', []);
            }
        }
    }

    /**
     * 删除session数据
     * @access public
     * @param  string|array  $name session名称
     * @return void
     */
    public function delete($name): void
    {
        empty($this->init) && $this->boot();

        if (is_array($name)) {
            foreach ($name as $key) {
                $this->delete($key);
            }
        } elseif (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);

            unset($_SESSION[$name1][$name2]);
        } else {
            unset($_SESSION[$name]);
        }
    }

    /**
     * 清空session数据
     * @access public
     * @return void
     */
    public function clear(): void
    {
        empty($this->init) && $this->boot();

        $_SESSION = [];
    }

    /**
     * 判断session数据
     * @access public
     * @param  string       $name session名称
     * @return bool
     */
    public function has(string $name): bool
    {
        empty($this->init) && $this->boot();

        $value = $_SESSION;

        $name = explode('.', $name);

        foreach ($name as $val) {
            if (!isset($value[$val])) {
                return false;
            } else {
                $value = $value[$val];
            }
        }

        return true;
    }

    /**
     * 添加数据到一个session数组
     * @access public
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function push(string $key, $value): void
    {
        $array = $this->get($key);

        if (is_null($array)) {
            $array = [];
        }

        $array[] = $value;

        $this->set($key, $array);
    }

    /**
     * 启动session
     * @access public
     * @return void
     */
    public function start(): void
    {
        session_start();

        $this->init = true;
    }

    /**
     * 销毁session
     * @access public
     * @return void
     */
    public function destroy(): void
    {
        if (!empty($_SESSION)) {
            $_SESSION = [];
        }

        session_unset();
        session_destroy();

        $this->init       = null;
        $this->lockDriver = null;
    }

    /**
     * 重新生成session_id
     * @access public
     * @param  bool $delete 是否删除关联会话文件
     * @return void
     */
    public function regenerate(bool $delete = false): void
    {
        session_regenerate_id($delete);
    }

    /**
     * 暂停session
     * @access public
     * @return void
     */
    public function pause(): void
    {
        // 暂停session
        session_write_close();
        $this->init = false;
    }
}
