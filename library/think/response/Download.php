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

namespace think\response;

use think\Exception;
use think\Response;

class Download extends Response
{
    protected $expire = 360;
    protected $name;
    protected $mimeType;

    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return mixed
     * @throws \Exception
     */
    protected function output($data)
    {
        if (!is_file($data)) {
            throw new Exception('file not exists:' . $data);
        }
        ob_end_clean();

        if (!empty($this->name)) {
            $filename = $this->name;
        } else {
            $filename = pathinfo($data, PATHINFO_BASENAME);
        }

        $mimeType = $this->getMimeType($data) ?: 'application/octet-stream';

        $this->header['Pragma']                    = 'public';
        $this->header['Content-Type']              = $mimeType;
        $this->header['Cache-control']             = 'max-age=' . $this->expire;
        $this->header['Content-Disposition']       = 'attachment; filename="' . $filename . '"';
        $this->header['Content-Length']            = filesize($data);
        $this->header['Content-Transfer-Encoding'] = 'binary';
        $this->header['Expires']                   = gmdate("D, d M Y H:i:s", time() + $this->expire) . ' GMT';

        $this->lastModified(date('D, d M Y H:i:s', time()) . ' GMT');

        $data = file_get_contents($data);
        return $data;
    }

    /**
     * 设置有效期
     * @access public
     * @param  integer $expire 有效期
     * @return $this
     */
    public function expire($expire)
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * 设置文件类型
     * @access public
     * @param  string $filename 文件名
     * @return $this
     */
    public function mimeType($mimeType)
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * 获取文件类型信息
     * @access public
     * @param  string $filename 文件名
     * @return string
     */
    protected function getMimeType($filename)
    {
        if (!empty($this->mimeType)) {
            return $this->mimeType;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        return finfo_file($finfo, $filename);
    }

    /**
     * 设置下载文件的显示名称
     * @access public
     * @param  string $filename 文件名
     * @param  bool   $extension 后缀自动识别
     * @return $this
     */
    public function name($filename, $extension = true)
    {
        $this->name = $filename;

        if ($extension && !strpos($filename, '.')) {
            $this->name .= pathinfo($this->data, PATHINFO_EXTENSION);
        }

        return $this;
    }
}
