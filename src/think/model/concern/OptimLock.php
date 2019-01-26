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

use think\Exception;

/**
 * 乐观锁
 */
trait OptimLock
{
    protected function getOptimLockField()
    {
        return property_exists($this, 'optimLock') && isset($this->optimLock) ? $this->optimLock : 'lock_version';
    }

    /**
     * 数据检查
     * @access protected
     * @return void
     */
    protected function checkData(): void
    {
        $this->isExists() ? $this->updateLockVersion() : $this->recordLockVersion();
    }

    /**
     * 记录乐观锁
     * @access protected
     * @return void
     */
    protected function recordLockVersion(): void
    {
        $optimLock = $this->getOptimLockField();

        if ($optimLock && !isset($this->data[$optimLock])) {
            $this->data[$optimLock]   = 0;
            $this->origin[$optimLock] = 0;
        }
    }

    /**
     * 更新乐观锁
     * @access protected
     * @return bool
     */
    protected function updateLockVersion(): void
    {
        $optimLock = $this->getOptimLockField();

        if ($optimLock && isset($this->data[$optimLock])) {
            // 更新乐观锁
            $this->data[$optimLock]++;
        }
    }

    protected function getUpdateWhere(&$data)
    {
        // 保留主键数据
        foreach ($this->data as $key => $val) {
            if ($this->isPk($key)) {
                $data[$key] = $val;
            }
        }

        $pk    = $this->getPk();
        $array = [];

        foreach ((array) $pk as $key) {
            if (isset($data[$key])) {
                $array[] = [$key, '=', $data[$key]];
                unset($data[$key]);
            }
        }

        if (!empty($array)) {
            $where = $array;
        } else {
            $where = $this->updateWhere;
        }

        $optimLock = $this->getOptimLockField();

        if ($optimLock && isset($this->origin[$optimLock])) {
            $where[] = [$optimLock, '=', $this->origin[$optimLock]];
        }

        return $where;
    }

    protected function checkResult($result)
    {
        if (!$result) {
            throw new Exception('record has update');
        }
    }

}
