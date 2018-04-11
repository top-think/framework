<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\cache\driver;

use think\cache\Driver;

/**
 * 文件类型缓存类
 * @author    liu21st <liu21st@gmail.com>
 */
class Lite extends Driver
{
    protected $options = [
        'prefix' => '',
        'path'   => '',
        'expire' => 0, // 等于 10*365*24*3600（10年）
    ];

    /**
     * 架构函数
     * @access public
     *
     * @param  array $options
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (substr($this->options['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->options['path'] .= DIRECTORY_SEPARATOR;
        }

    }

    /**
     * 取得变量的存储文件名
     * @access protected
     * @param  string $name 缓存变量名
     * @return string
     */
    protected function getCacheKey(string $name)
    {
        return $this->options['path'] . $this->options['prefix'] . md5($name) . '.php';
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param  string $name 缓存变量名
     * @return mixed
     */
    public function has(string $name)
    {
        return $this->get($name) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $name, $default = false)
    {
        $this->readTimes++;

        $filename = $this->getCacheKey($name);

        if (is_file($filename)) {
            // 判断是否过期
            $mtime = filemtime($filename);

            if ($mtime < time()) {
                // 清除已经过期的文件
                unlink($filename);
                return $default;
            }

            return include $filename;
        } else {
            return $default;
        }
    }

    /**
     * 写入缓存
     * @access public
     * @param  string        $name  缓存变量名
     * @param  mixed         $value 存储数据
     * @param  int|\DateTime $expire 有效时间 0为永久
     * @return bool
     */
    public function set(string $name, $value, $expire = null)
    {
        $this->writeTimes++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp();
        } else {
            $expire = 0 === $expire ? 10 * 365 * 24 * 3600 : $expire;
            $expire = time() + $expire;
        }

        $filename = $this->getCacheKey($name);

        if ($this->tag && !is_file($filename)) {
            $first = true;
        }

        $ret = file_put_contents($filename, ("<?php return " . var_export($value, true) . ";"));

        // 通过设置修改时间实现有效期
        if ($ret) {
            isset($first) && $this->setTagItem($filename);
            touch($filename, $expire);
        }

        return $ret;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc(string $name, int $step = 1)
    {
        if ($this->has($name)) {
            $value = $this->get($name) + $step;
        } else {
            $value = $step;
        }

        return $this->set($name, $value, 0) ? $value : false;
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec(string $name, int $step = 1)
    {
        if ($this->has($name)) {
            $value = $this->get($name) - $step;
        } else {
            $value = -$step;
        }

        return $this->set($name, $value, 0) ? $value : false;
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return boolean
     */
    public function rm(string $name)
    {
        $this->writeTimes++;

        return unlink($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return bool
     */
    public function clear(? string $tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                unlink($key);
            }

            $this->rm('tag_' . md5($tag));
            return true;
        }

        $this->writeTimes++;

        array_map("unlink", glob($this->options['path'] . ($this->options['prefix'] ? $this->options['prefix'] . DIRECTORY_SEPARATOR : '') . '*.php'));
    }
}
