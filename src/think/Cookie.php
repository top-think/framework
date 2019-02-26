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

class Cookie
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => false,
        // 是否使用 setcookie
        'setcookie' => true,
    ];

    /**
     * 是否初始化
     * @var bool
     */
    protected $init;

    /**
     * 构造方法
     * @access public
     */
    public function __construct(array $config = [])
    {
        $this->init($config);
    }

    /**
     * Cookie初始化
     * @access public
     * @param  array $config
     * @return void
     */
    public function init(array $config = []): void
    {
        $this->config = array_merge($this->config, array_change_key_case($config));

        if (!empty($this->config['httponly']) && PHP_SESSION_ACTIVE != session_status()) {
            ini_set('session.cookie_httponly', '1');
        }
    }

    public static function __make(Config $config)
    {
        return new static($config->get('cookie', []));
    }

    /**
     * 设置或者获取cookie作用域（前缀）
     * @access public
     * @param  string $prefix
     * @return string|void
     */
    public function prefix(string $prefix = '')
    {
        if (empty($prefix)) {
            return $this->config['prefix'];
        }

        $this->config['prefix'] = $prefix;
    }

    /**
     * Cookie 设置
     *
     * @access public
     * @param  string               $name  cookie名称
     * @param  mixed                $value cookie值
     * @param  null|integer|array   $option 可选参数 可能会是
     * @return void
     */
    public function set(string $name, $value = '', $option = null): void
    {
        !isset($this->init) && $this->init();

        // 参数设置(会覆盖黙认设置)
        if (!is_null($option)) {
            if (is_numeric($option)) {
                $option = ['expire' => $option];
            }

            $config = array_merge($this->config, array_change_key_case($option));
        } else {
            $config = $this->config;
        }

        $name = $config['prefix'] . $name;

        // 设置cookie
        if (is_array($value)) {
            array_walk_recursive($value, [$this, 'jsonFormatProtect'], 'encode');
            $value = 'think:' . json_encode($value);
        }

        $expire = !empty($config['expire']) ? $_SERVER['REQUEST_TIME'] + intval($config['expire']) : 0;

        if ($config['setcookie']) {
            $this->setCookie($name, (string) $value, $expire, $config);
        }

        $_COOKIE[$name] = $value;
    }

    /**
     * Cookie 设置保存
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  mixed  $value cookie值
     * @param  int    $expire 有效期
     * @param  array  $option 可选参数
     * @return void
     */
    protected function setCookie(string $name, string $value, int $expire, array $option = []): void
    {
        setcookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
    }

    /**
     * 永久保存Cookie数据
     * @access public
     * @param  string $name  cookie名称
     * @param  mixed  $value cookie值
     * @param  mixed  $option 可选参数 可能会是 null|integer|string
     * @return void
     */
    public function forever(string $name, $value = '', $option = null): void
    {
        if (is_null($option) || is_numeric($option)) {
            $option = [];
        }

        $option['expire'] = 315360000;

        $this->set($name, $value, $option);
    }

    /**
     * 判断Cookie数据
     * @access public
     * @param  string        $name cookie名称
     * @param  string|null   $prefix cookie前缀
     * @return bool
     */
    public function has(string $name, string $prefix = null): bool
    {
        !isset($this->init) && $this->init();

        $prefix = !is_null($prefix) ? $prefix : $this->config['prefix'];
        $name   = $prefix . $name;

        return isset($_COOKIE[$name]);
    }

    /**
     * Cookie获取
     * @access public
     * @param  string        $name cookie名称 留空获取全部
     * @param  string|null   $prefix cookie前缀
     * @return mixed
     */
    public function get(string $name = '', string $prefix = null)
    {
        !isset($this->init) && $this->init();

        $prefix = !is_null($prefix) ? $prefix : $this->config['prefix'];
        $key    = $prefix . $name;

        if ('' == $name) {
            if ($prefix) {
                $value = [];
                foreach ($_COOKIE as $k => $val) {
                    if (0 === strpos($k, $prefix)) {
                        $value[$k] = $val;
                    }
                }
            } else {
                $value = $_COOKIE;
            }
        } elseif (isset($_COOKIE[$key])) {
            $value = $_COOKIE[$key];

            if (0 === strpos($value, 'think:')) {
                $value = substr($value, 6);
                $value = json_decode($value, true);
                array_walk_recursive($value, [$this, 'jsonFormatProtect'], 'decode');
            }
        } else {
            $value = null;
        }

        return $value;
    }

    /**
     * Cookie删除
     * @access public
     * @param  string        $name cookie名称
     * @param  string|null   $prefix cookie前缀
     * @return void
     */
    public function delete(string $name, string $prefix = null): void
    {
        !isset($this->init) && $this->init();

        $config = $this->config;
        $prefix = !is_null($prefix) ? $prefix : $config['prefix'];
        $name   = $prefix . $name;

        if ($config['setcookie']) {
            $this->setCookie($name, '', $_SERVER['REQUEST_TIME'] - 3600, $config);
        }

        // 删除指定cookie
        unset($_COOKIE[$name]);
    }

    /**
     * Cookie清空
     * @access public
     * @param  string|null $prefix cookie前缀
     * @return void
     */
    public function clear(string $prefix = null): void
    {
        // 清除指定前缀的所有cookie
        if (empty($_COOKIE)) {
            return;
        }

        !isset($this->init) && $this->init();

        // 要删除的cookie前缀，不指定则删除config设置的指定前缀
        $config = $this->config;
        $prefix = !is_null($prefix) ? $prefix : $config['prefix'];

        if ($prefix) {
            // 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                if (0 === strpos($key, $prefix)) {
                    if ($config['setcookie']) {
                        $this->setCookie($key, '', $_SERVER['REQUEST_TIME'] - 3600, $config);
                    }
                    unset($_COOKIE[$key]);
                }
            }
        }

        return;
    }

    private function jsonFormatProtect(&$val, $key, $type = 'encode')
    {
        if (!empty($val) && true !== $val) {
            $val = 'decode' == $type ? urldecode($val) : urlencode((string) $val);
        }
    }

}
