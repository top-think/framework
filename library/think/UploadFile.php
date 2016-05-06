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

class UploadFile
{

    /**
     * 上传文件信息
     * @var array
     */
    protected $info = [];

    /**
     * 上传错误信息
     * @var string
     */
    private $error = '';

    public function __construct($file)
    {
        $this->info = $file;
    }

    protected function checkPath($path)
    {

        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, 0777, true)) {
            return true;
        } else {
            $this->error = "目录 {$savepath} 创建失败！";
            return false;
        }
    }

    /**
     * 移动文件
     * @param  string   $path    保存路径
     * @param  string   $savename    保存的文件名
     * @param  boolean $replace 同名文件是否覆盖
     * @return boolean          保存状态，true-成功，false-失败
     */
    public function moveTo($path, $savename = '', $replace = true)
    {
        if (false === $this->checkPath($path)) {
            return false;
        }

        $savename = $savename ?: $this->info['name'];
        /* 不覆盖同名文件 */
        if (!$replace && is_file($path . $savename)) {
            $this->error = '存在同名文件' . $path . $savename;
            return false;
        }

        /* 移动文件 */
        if (!move_uploaded_file($this->info['tmp_name'], $path . $savename)) {
            $this->error = '文件上传保存错误！';
            return false;
        }
        $this->info['path']     = $path;
        $this->info['savename'] = $savename;
        return true;
    }

    /**
     * 获取文件信息
     * @param  string   $path    保存路径
     * @param  string   $savename    保存的文件名
     * @param  boolean $replace 同名文件是否覆盖
     * @return mixed          保存状态，true-成功，false-失败
     */
    public function getInfo($name = '')
    {
        return isset($this->info[$name]) ? $this->info[$name] : $this->info;
    }

}
