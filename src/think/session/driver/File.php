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

use think\App;
use think\session\SessionHandler;

class File implements SessionHandler
{
    protected $config = [
        'path'          => '',
        'expire'        => 0,
        'cache_subdir'  => true,
        'data_compress' => false,
        'serialize'     => ['serialize', 'unserialize'],
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->config = array_merge($this->config, $config);

        if (empty($this->config['path'])) {
            $this->config['path'] = $app->getRuntimePath() . 'session' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }

        $this->init();
    }

    /**
     * 初始化检查
     * @access private
     * @return bool
     */
    private function init(): bool
    {
        // 创建项目缓存目录
        try {
            if (!is_dir($this->config['path']) && mkdir($this->config['path'], 0755, true)) {
                return true;
            }
        } catch (\Exception $e) {
            // 写入失败
        }

        return false;
    }

    /**
     * 取得变量的存储文件名
     * @access protected
     * @param  string $name 缓存变量名
     * @param  bool   $auto 是否自动创建目录
     * @return string
     */
    protected function getFileName(string $name, bool $auto = false): string
    {
        if ($this->config['cache_subdir']) {
            // 使用子目录
            $name = substr($name, 0, 2) . DIRECTORY_SEPARATOR . 'sess_' . $name;
        } else {
            $name = 'sess_' . $name;
        }

        $filename = $this->config['path'] . $name . '.php';
        $dir      = dirname($filename);

        if ($auto && !is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (\Exception $e) {
                // 创建失败
            }
        }

        return $filename;
    }

    /**
     * 读取Session
     * @access public
     * @param  string $sessionId
     * @return array
     */
    public function read(string $sessionId): array
    {
        $filename = $this->getFileName($sessionId);

        if (!is_file($filename)) {
            return [];
        }

        $content = file_get_contents($filename);

        if (false !== $content) {
            $expire = (int) substr($content, 8, 12);
            if (0 != $expire && time() > filemtime($filename) + $expire) {
                //缓存过期删除缓存文件
                $this->unlink($filename);
                return [];
            }

            $content = substr($content, 32);

            if ($this->config['data_compress'] && function_exists('gzcompress')) {
                //启用数据压缩
                $content = gzuncompress($content);
            }
            return $this->unserialize($content);
        } else {
            return [];
        }
    }

    /**
     * 写入Session
     * @access public
     * @param  string $sessionId
     * @param  array  $data
     * @return array
     */
    public function write(string $sessionId, array $data): bool
    {
        $expire = $this->config['expire'];

        $expire   = $this->getExpireTime($expire);
        $filename = $this->getFileName($sessionId, true);

        $data = $this->serialize($data);

        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data, 3);
        }

        $data   = "<?php\n//" . sprintf('%012d', $expire) . "\n exit();?>\n" . $data;
        $result = file_put_contents($filename, $data);

        if ($result) {
            clearstatcache();
            return true;
        }

        return false;
    }

    /**
     * 删除Session
     * @access public
     * @param  string $sessionId
     * @return array
     */
    public function delete(string $sessionId): bool
    {
        try {
            return $this->unlink($this->getFileName($sessionId));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取有效期
     * @access protected
     * @param  integer|\DateTimeInterface $expire 有效期
     * @return int
     */
    protected function getExpireTime($expire): int
    {
        if ($expire instanceof \DateTimeInterface) {
            $expire = $expire->getTimestamp() - time();
        }

        return (int) $expire;
    }

    /**
     * 判断文件是否存在后，删除
     * @access private
     * @param  string $path
     * @return bool
     */
    private function unlink(string $file): bool
    {
        return is_file($file) && unlink($file);
    }

    /**
     * 序列化数据
     * @access protected
     * @param  mixed $data
     * @return string
     */
    protected function serialize($data): string
    {
        $serialize = $this->config['serialize'][0];

        return $serialize($data);
    }

    /**
     * 反序列化数据
     * @access protected
     * @param  string $data
     * @return mixed
     */
    protected function unserialize(string $data)
    {
        $unserialize = $this->config['serialize'][1];

        return $unserialize($data);
    }

}
