<?php

namespace traits\model;

trait SoftDelete
{

    /**
     * 判断当前实例是否被软删除
     * @access public
     * @return boolean
     */
    public function trashed()
    {
        if (!empty($this->data[static::$deleteTime])) {
            return true;
        }
        return false;
    }

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
        return $model->db()->where(static::$deleteTime, 'exp', 'is not null');
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
     * 删除记录
     * @access public
     * @param mixed $data 主键列表 支持闭包查询条件
     * @param bool  $force 是否强制删除
     * @return integer 成功删除的记录数
     */
    public static function destroy($data, $force = false)
    {
        $model = new static();
        $query = $model->db();
        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $query]);
            $data = null;
        }
        $resultSet = $query->select($data);
        $count     = 0;
        if ($resultSet) {
            foreach ($resultSet as $data) {
                $result = $data->delete($force);
                $count += $result;
            }
        }
        return $count;
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
            $name              = static::$deleteTime;
            $this->change[]    = $name;
            $this->data[$name] = null;
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
            $query->where(static::$deleteTime, 'null');
        }
    }

}
