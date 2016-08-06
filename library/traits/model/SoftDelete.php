<?php

namespace traits\model;

trait SoftDelete
{
    /**
     * 查询软删除数据
     * @access public
     * @return \think\db\Query
     */
    public static function withTrashed()
    {
        $model = new static();
        return $model->db();
    }

    /**
     * 只查询软删除数据
     * @access public
     * @return \think\db\Query
     */
    public static function onlyTrashed()
    {
        $model = new static();
        return $model->db()->where(static::$deleteTime, '>', 0);
    }

    /**
     * 删除当前的记录
     * @access public
     * @param bool  $force 是否强制删除
     * @return integer
     */
    public function delete($force = false)
    {
        if (false === $this->trigger('before_delete', $this)) {
            return false;
        }

        if (static::$deleteTime && !$force) {
            // 软删除
            $name              = static::$deleteTime;
            $this->change[]    = $name;
            $this->data[$name] = $this->autoWriteTimestamp($name);
            $result            = $this->isUpdate()->save();
        } else {
            $result = $this->db()->delete($this->data);
        }

        $this->trigger('after_delete', $this);
        return $result;
    }

    /**
     * 恢复被软删除的记录
     * @access public
     * @return integer
     */
    public function restore()
    {
        if (static::$deleteTime) {
            // 恢复删除
            $this->setAttr(static::$deleteTime, 0);
            return $this->isUpdate()->save();
        }
        return false;
    }

    /**
     * 查询默认不包含软删除数据
     * @access protected
     * @return void
     */
    protected static function base($query)
    {
        if (static::$deleteTime) {
            $query->where(static::$deleteTime, 0);
        }
    }

}
