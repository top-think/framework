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
    // 当前完整文件名
    protected $filename;
    // 文件上传命名规则
    protected $rule = 'date';
    // 单元测试
    protected $isTest;
    // 上传文件信息
    protected $info;

    public function __construct($filename, $mode = 'r')
    {
        parent::__construct($filename, $mode);
        $this->filename = $this->getRealPath();
    }

    /**
     * 是否测试
     * @param  bool   $test 是否测试
     * @return $this
     */
    public function isTest($test = false)
    {
        $this->isTest = $test;
        return $this;
    }

    /**
     * 设置上传信息
     * @param  array   $info 上传文件信息
     * @return $this
     */
    public function setUploadInfo($info)
    {
        $this->info = $info;
        return $this;
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
        return finfo_file($finfo, $this->filename);
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
        if ($this->isTest) {
            return is_file($this->filename);
        }
        return is_uploaded_file($this->filename);
    }

    /**
     * 移动文件
     * @param  string           $path    保存路径
     * @param  string|bool      $savename    保存的文件名 默认自动生成
     * @param  boolean          $replace 同名文件是否覆盖
     * @return false|SplFileInfo false-失败 否则返回SplFileInfo实例
     */
    public function move($path, $savename = true, $replace = true)
    {
        // 检测合法性
        if (!$this->isValid()) {
            $this->error = '非法上传文件';
            return false;
        }

        $path = rtrim($path, DS) . DS;
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
        if ($this->isTest) {
            rename($this->filename, $path . $savename);
        } elseif (!move_uploaded_file($this->filename, $path . $savename)) {
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
                        $md5      = md5_file($this->filename);
                        $savename = substr($md5, 0, 2) . DS . substr($md5, 2);
                        break;
                    case 'sha1':
                        $sha1     = sha1_file($this->filename);
                        $savename = substr($sha1, 0, 2) . DS . substr($sha1, 2);
                        break;
                    case 'date':
                        $savename = date('Ymd') . DS . md5(microtime(true));
                        break;
                    default:
                        $savename = call_user_func($this->rule);
                }
            }
            if (!strpos($savename, '.')) {
                $savename .= '.' . pathinfo($this->getInfo('name'), PATHINFO_EXTENSION);
            }
        } elseif ('' === $savename) {
            $savename = $this->getInfo('name');
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
