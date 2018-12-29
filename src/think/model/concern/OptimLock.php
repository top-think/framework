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
declare (strict_types = 1);

namespace think\model\concern;

use think\Exception;
use think\Model;

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
     * 创建新的模型实例
     * @access public
     * @param  array    $data 数据
     * @param  bool     $isUpdate 是否为更新
     * @param  mixed    $where 更新条件
     * @return Model
     */
    public function newInstance(array $data = [], bool $isUpdate = false, $where = null): Model
    {
        // 缓存乐观锁
        $this->cacheLockVersion($data);

        return (new static($data))->isUpdate($isUpdate, $where);
    }

    /**
     * 数据检查
     * @access protected
     * @param  array $data 数据
     * @return void
     */
    protected function checkData(array &$data = []): void
    {
        if ($this->isExists()) {
            if (!$this->checkLockVersion($data)) {
                throw new Exception('record has update');
            }
        } else {
            $this->recordLockVersion($data);
        }
    }

    /**
     * 记录乐观锁
     * @access protected
     * @param  array $data 数据
     * @return void
     */
    protected function recordLockVersion(&$data): void
    {
        $optimLock = $this->getOptimLockField();

        if ($optimLock && !isset($data[$optimLock])) {
            $data[$optimLock] = 0;
        }
    }

    /**
     * 缓存乐观锁
     * @access protected
     * @param  array $data 数据
     * @return void
     */
    protected function cacheLockVersion($data): void
    {
        $optimLock = $this->getOptimLockField();
        $pk        = $this->getPk();

        if ($optimLock && isset($data[$optimLock]) && is_string($pk) && isset($data[$pk])) {
            $key = $this->getName() . '_' . $data[$pk] . '_lock_version';

            $_SESSION[$key] = $data[$optimLock];
        }
    }

    /**
     * 检查乐观锁
     * @access protected
     * @param  array $data 数据
     * @return bool
     */
    protected function checkLockVersion(array &$data): bool
    {
        // 检查乐观锁
        $id = $this->getKey();

        if (empty($id)) {
            return true;
        }

        $key       = $this->getName() . '_' . $id . '_lock_version';
        $optimLock = $this->getOptimLockField();

        if ($optimLock && isset($_SESSION[$key])) {
            $lockVer        = $_SESSION[$key];
            $vo             = $this->field($optimLock)->find($id);
            $_SESSION[$key] = $lockVer;
            $currVer        = $vo[$optimLock];

            if (isset($currVer)) {
                if ($currVer > 0 && $lockVer != $currVer) {
                    // 记录已经更新
                    return false;
                }

                // 更新乐观锁
                $lockVer++;

                if ($data[$optimLock] != $lockVer) {
                    $data[$optimLock] = $lockVer;
                }

                $_SESSION[$key] = $lockVer;
            }
        }

        return true;
    }
}
