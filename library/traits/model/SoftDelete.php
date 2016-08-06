<?php

namespace traits\model;

trait SoftDelete
{
    /**
     * 分析查询表达式
     * @access public
     * @param mixed         $data 主键列表或者查询条件（闭包）
     * @param string        $with 关联预查询
     * @param bool          $cache 是否缓存
     * @return Query
     */
    protected static function parseQuery(&$data, $with, $cache)
    {
        $result = self::with($with)->cache($cache);
        if (is_array($data) && key($data) !== 0) {
            $result = $result->where($data);
            $data   = null;
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $result]);
            $data = null;
        } elseif ($data instanceof Query) {
            $result = $data->with($with)->cache($cache);
            $data   = null;
        }

        if (static::$deleteTime) {
            // 默认不查询软删除数据
            $result->where(static::$deleteTime, 0);
        }
        return $result;
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

    public function __call($method, $args)
    {
        if (method_exists($this, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $this->db());
            call_user_func_array([$this, $method], $args);
            return $this;
        } else {
            $query = $this->db();
            $query->where(static::$deleteTime, 0);
            return call_user_func_array([$this->db(), $method], $args);
        }
    }

    public static function __callStatic($method, $params)
    {
        $model = get_called_class();
        if (!isset(self::$links[$model])) {
            self::$links[$model] = (new static())->db();
        }
        $query = self::$links[$model];
        $query->where(static::$deleteTime, 0);
        return call_user_func_array([$query, $method], $params);
    }
}
