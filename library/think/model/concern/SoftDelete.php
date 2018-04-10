<?php

namespace think\model\concern;

use think\db\Query;

/**
 * 数据软删除
 */
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

        if ($field && !empty($this->getOrigin($field))) {
            return true;
        }

        return false;
    }

    /**
     * 查询软删除数据
     * @access public
     * @return Query
     */
    public static function withTrashed()
    {
        $model = new static();

        return $model->db(false);
    }

    /**
     * 只查询软删除数据
     * @access public
     * @return Query
     */
    public static function onlyTrashed()
    {
        $model = new static();
        $field = $model->getDeleteTimeField(true);

        if ($field) {
            return $model
                ->db(false)
                ->useSoftDelete($field, $model->getWithTrashedExp());
        }

        return $model->db(false);
    }

    /**
     * 获取软删除数据的查询条件
     * @access protected
     * @return array
     */
    protected function getWithTrashedExp()
    {
        return is_null($this->defaultSoftDelete) ?
        ['notnull', ''] : ['<>', $this->defaultSoftDelete];
    }

    /**
     * 删除当前的记录
     * @access public
     * @param  bool  $force 是否强制删除
     * @return integer
     */
    public function delete($force = false)
    {
        if (false === $this->trigger('before_delete', $this)) {
            return false;
        }

        $name = $this->getDeleteTimeField();

        if ($name && !$force) {
            // 软删除
            $this->data($name, $this->autoWriteTimestamp($name));

            $result = $this->isUpdate()->save();
        } else {
            // 读取更新条件
            $where = $this->getWhere();

            // 删除当前模型数据
            $result = $this->db(false)->where($where)->delete();
        }

        // 关联删除
        if (!empty($this->relationWrite)) {
            $this->autoRelationDelete();
        }

        $this->trigger('after_delete', $this);

        // 清空数据
        $this->data   = [];
        $this->origin = [];

        return $result;
    }

    /**
     * 删除记录
     * @access public
     * @param  mixed $data 主键列表 支持闭包查询条件
     * @param  bool  $force 是否强制删除
     * @return integer 成功删除的记录数
     */
    public static function destroy($data, $force = false)
    {
        // 包含软删除数据
        $query = self::withTrashed();

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
     * @param  array $where 更新条件
     * @return integer
     */
    public function restore($where = [])
    {
        $name = $this->getDeleteTimeField();

        if (empty($where)) {
            $pk = $this->getPk();

            $where[] = [$pk, '=', $this->getData($pk)];
        }

        if ($name) {
            // 恢复删除
            return $this->db(false)
                ->where($where)
                ->useSoftDelete($name, $this->getWithTrashedExp())
                ->update([$name => $this->defaultSoftDelete]);
        }

        return 0;
    }

    /**
     * 获取软删除字段
     * @access protected
     * @param  bool  $read 是否查询操作 写操作的时候会自动去掉表别名
     * @return string|false
     */
    protected function getDeleteTimeField($read = false)
    {
        $field = property_exists($this, 'deleteTime') && isset($this->deleteTime) ? $this->deleteTime : 'delete_time';

        if (false === $field) {
            return false;
        }

        if (!strpos($field, '.')) {
            $field = '__TABLE__.' . $field;
        }

        if (!$read && strpos($field, '.')) {
            $array = explode('.', $field);
            $field = array_pop($array);
        }

        return $field;
    }

    /**
     * 查询的时候默认排除软删除数据
     * @access protected
     * @param  Query  $query
     * @return void
     */
    protected function withNoTrashed($query)
    {
        $field = $this->getDeleteTimeField(true);

        if ($field) {
            $query->useSoftDelete($field, $this->defaultSoftDelete);
        }
    }
}
