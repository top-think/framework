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

namespace think;

use SplFileObject;

class File extends SplFileObject
{
    /**
     * 错误信息
     * @var string
     */
    private $error = '';
    // 文件上传命名规则
    protected $rule = 'date';

    // 上传文件信息
    protected $info;

    public function __construct($filename, $info = [])
    {
        parent::__construct($filename);
        $this->info = $info;
    }

    /**
     * 获取上传文件的信息
     * @param  string   $name
     * @return array|string
     */
    public function getInfo($name = '')
    {
        return isset($this->info[$name]) ? $this->info[$name] : $this->info;
    }

    /**
     * 检查目录是否可写
     * @param  string   $path    目录
     * @return boolean
     */
    protected function checkPath($path)
    {
        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, 0777, true)) {
            return true;
        } else {
            $this->error = "目录 {$path} 创建失败！";
            return false;
        }
    }

    /**
     * 获取文件类型信息
     * @return string
     */
    public function getMime()
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $this->getRealPath());
    }

    /**
     * 设置文件的命名规则
     * @param  string   $rule    文件命名规则
     * @return $this
     */
    public function rule($rule)
    {
        $this->rule = $rule;
        return $this;
    }

    /**
     * 检测是否合法的上传文件
     * @return bool
     */
    public function isValid()
    {
        return is_uploaded_file($this->getRealPath());
    }

    /**
     * 移动文件
     * @param  string   $path    保存路径
     * @param  string|bool   $savename    保存的文件名 默认自动生成
     * @param  boolean $replace 同名文件是否覆盖
     * @return false|SplFileInfo false-失败 否则返回SplFileInfo实例
     */
    public function move($path, $savename = true, $replace = true)
    {
        // 检测合法性
        if (!$this->isValid()) {
            $this->error = '非法上传文件';
            return false;
        }

        // 文件保存命名规则
        $savename = $this->getSaveName($savename);

        // 检测目录
        if (false === $this->checkPath(dirname($path . $savename))) {
            return false;
        }

        /* 不覆盖同名文件 */
        if (!$replace && is_file($path . $savename)) {
            $this->error = '存在同名文件' . $path . $savename;
            return false;
        }

        /* 移动文件 */
        if (!move_uploaded_file($this->getRealPath(), $path . $savename)) {
            $this->error = '文件上传保存错误！';
            return false;
        }

        return new \SplFileInfo($path . $savename);
    }

    /**
     * 获取保存文件名
     * @param  string|bool   $savename    保存的文件名 默认自动生成
     * @return string
     */
    protected function getSaveName($savename)
    {
        if (true === $savename) {
            // 自动生成文件名
            if ($this->rule instanceof \Closure) {
                $savename = call_user_func_array($this->rule, [$this]);
            } else {
                switch ($this->rule) {
                    case 'md5':
                        $md5      = md5_file($this->getRealPath());
                        $savename = substr($md5, 0, 2) . DS . substr($md5, 2);
                        break;
                    case 'sha1':
                        $sha1     = sha1_file($this->getRealPath());
                        $savename = substr($sha1, 0, 2) . DS . substr($sha1, 2);
                        break;
                    case 'date':
                        $savename = date('Y-m-d') . DS . md5(microtime(true));
                        break;
                    default:
                        $savename = call_user_func($this->rule);
                }
            }
            if (!strpos($savename, '.')) {
                $savename .= '.' . pathinfo($this->getInfo('name'), PATHINFO_EXTENSION);
            }
        } elseif ('' === $savename) {
            $savename = $this->getFilename();
        }
        return $savename;
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }
}
