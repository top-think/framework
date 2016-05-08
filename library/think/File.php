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
     * 移动文件
     * @param  string   $path    保存路径
     * @param  string   $savename    保存的文件名
     * @param  boolean $replace 同名文件是否覆盖
     * @return false|SplFileInfo false-失败 否则返回SplFileInfo实例
     */
    public function move($path, $savename = '', $replace = true)
    {
        if (!is_uploaded_file($this->getRealPath())) {
            return false;
        }

        if (false === $this->checkPath($path)) {
            return false;
        }

        $savename = $savename ?: $this->getFilename();
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

        return new SplFileInfo($path . $savename);
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
