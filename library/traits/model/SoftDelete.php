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
        $field = $this->getDeleteTimeField();
        if (!empty($this->data[$field])) {
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
        $field = $model->getDeleteTimeField();
        return $model->db()->where($field, 'exp', 'is not null');
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
        $name = $this->getDeleteTimeField();
        if (!$force) {
            // 软删除
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
        } elseif (is_null($data)) {
            return 0;
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
     * @param array $where 更新条件
     * @return integer
     */
    public function restore($where = [])
    {
        $name = $this->getDeleteTimeField();
        // 恢复删除
        return $this->isUpdate()->save([$name => null], $where);

    }

    /**
     * 查询默认不包含软删除数据
     * @access protected
     * @param \think\db\Query $query 查询对象
     * @return void
     */
    protected function base($query)
    {
        $field = $this->getDeleteTimeField(true);
        $query->where($field, 'null');
    }

    /**
     * 获取软删除字段
     * @access public
     * @param bool  $read 是否查询操作 写操作的时候会自动去掉表别名
     * @return string
     */
    protected function getDeleteTimeField($read = false)
    {
        $field = isset($this->deleteTime) ? $this->deleteTime : 'delete_time';
        if (!strpos($field, '.')) {
            $field = $this->db(false)->getTable() . '.' . $field;
        }
        if (!$read && strpos($field, '.')) {
            list($alias, $field) = explode('.', $field);
        }
        return $field;
    }
}
