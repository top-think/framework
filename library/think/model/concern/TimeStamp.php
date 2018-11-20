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

namespace think\model\concern;

/**
 * 自动时间戳
 */
trait TimeStamp
{
    /**
     * 是否需要自动写入时间戳 如果设置为字符串 则表示时间字段的类型
     * @var bool|string
     */
    protected $autoWriteTimestamp;

    /**
     * 创建时间字段 false表示关闭
     * @var false|string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段 false表示关闭
     * @var false|string
     */
    protected $updateTime = 'update_time';

    /**
     * 时间字段显示格式
     * @var string
     */
    protected $dateFormat;

    /**
     * 时间日期字段格式化处理
     * @access protected
     * @param  mixed $time      时间日期表达式
     * @param  mixed $format    日期格式
     * @param  bool  $timestamp 是否进行时间戳转换
     * @return mixed
     */
    protected function formatDateTime($time, $format, $timestamp = false)
    {
        if (empty($time)) {
            return;
        }

        if (false !== strpos($format, '\\')) {
            $time = new $format($time);
        } elseif (!$timestamp && false !== $format) {
            $time = date($format, $time);
            if ((false !== strpos($format, '.'))) {
                //时间格式中如果包含点则自动追加小数位数,以获取毫秒数
                $suffix       = substr($format, strpos($format, '.') + 1);
                $microtime    = microtime(true);
                $milliseconds = substr($microtime, strpos($microtime, '.') + 1, strlen($suffix));
                $milliseconds = str_pad($milliseconds, strlen($suffix), "0", STR_PAD_RIGHT);
                $time         = str_replace($suffix, $milliseconds, $time);
            }
        }

        return $time;
    }

    /**
     * 检查时间字段写入
     * @access protected
     * @return void
     */
    protected function checkTimeStampWrite()
    {
        // 自动写入创建时间和更新时间
        if ($this->autoWriteTimestamp) {
            if ($this->createTime && !isset($this->data[$this->createTime])) {
                $this->data[$this->createTime] = $this->autoWriteTimestamp($this->createTime);
            }
            if ($this->updateTime && !isset($this->data[$this->updateTime])) {
                $this->data[$this->updateTime] = $this->autoWriteTimestamp($this->updateTime);
            }
        }
    }
}
