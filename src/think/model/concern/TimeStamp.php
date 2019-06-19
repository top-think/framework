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

namespace think\model\concern;

use DateTime;

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
     * 是否需要自动写入时间字段
     * @access public
     * @param  bool|string $auto
     * @return $this
     */
    public function isAutoWriteTimestamp($auto)
    {
        $this->autoWriteTimestamp = $auto;

        return $this;
    }

    /**
     * 获取自动写入时间字段
     * @access public
     * @return bool|string
     */
    public function getAutoWriteTimestamp()
    {
        return $this->autoWriteTimestamp;
    }

    /**
     * 设置时间字段格式化
     * @access public
     * @param  string|false $format
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * 获取自动写入时间字段
     * @access public
     * @return string|false
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * 自动写入时间戳
     * @access protected
     * @param  string $name 时间戳字段
     * @return mixed
     */
    protected function autoWriteTimestamp(string $name)
    {
        $value = time();

        if (isset($this->type[$name])) {
            $type = $this->type[$name];

            if (strpos($type, ':')) {
                list($type, $param) = explode(':', $type, 2);
            }

            switch ($type) {
                case 'datetime':
                case 'date':
                case 'timestamp':
                    $value = $this->formatDateTime('Y-m-d H:i:s.u');
                    break;
                default:
                    if (false !== strpos($type, '\\')) {
                        // 对象数据写入
                        $value = new $type();
                        if (method_exists($value, '__toString')) {
                            // 对象数据写入
                            $value = $value->__toString();
                        }
                    }
            }
        } elseif (is_string($this->autoWriteTimestamp) && in_array(strtolower($this->autoWriteTimestamp),
            ['datetime', 'date', 'timestamp'])) {
            $value = $this->formatDateTime('Y-m-d H:i:s.u');
        }

        return $value;
    }

    /**
     * 时间日期字段格式化处理
     * @access protected
     * @param  mixed $format    日期格式
     * @param  mixed $time      时间日期表达式
     * @param  bool  $timestamp 时间表达式是否为时间戳
     * @return mixed
     */
    protected function formatDateTime($format, $time = 'now', bool $timestamp = false)
    {
        if (empty($time)) {
            return;
        }

        if (false === $format) {
            return $time;
        } elseif (false !== strpos($format, '\\')) {
            return new $format($time);
        }

        if ($time instanceof DateTime) {
            $dateTime = $time;
        } elseif ($timestamp) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int) $time);
        } else {
            $dateTime = new DateTime($time);
        }

        return $dateTime->format($format);
    }

    /**
     * 获取时间字段值
     * @access protected
     * @param  mixed   $value
     * @return mixed
     */
    protected function getTimestampValue($value)
    {
        if (is_string($this->autoWriteTimestamp) && in_array(strtolower($this->autoWriteTimestamp), [
            'datetime', 'date', 'timestamp',
        ])) {
            $value = $this->formatDateTime($this->dateFormat, $value);
        } else {
            $value = $this->formatDateTime($this->dateFormat, $value, true);
        }

        return $value;
    }
}
