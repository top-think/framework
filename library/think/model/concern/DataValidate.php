<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\model\concern;

use think\exception\ValidateException;
use think\Facade;

/**
 * 模型数据验证
 */
trait DataValidate
{
    // 字段验证规则
    protected $validate;
    // 错误信息
    protected $error;
    // 是否采用批量验证
    protected $batchValidate = false;
    // 验证失败是否抛出异常
    protected $failException = false;

    /**
     * 设置字段验证
     * @access public
     * @param array|string|bool $rule  验证规则 true表示自动读取验证器类
     * @param array             $msg   提示信息
     * @param bool              $batch 批量验证
     * @return $this
     */
    public function validate($rule = true, $msg = [], $batch = false)
    {
        if (is_array($rule)) {
            $this->validate = [
                'rule' => $rule,
                'msg'  => $msg,
            ];
        } else {
            $this->validate = true === $rule ? $this->name : $rule;
        }

        $this->batchValidate = $batch;

        return $this;
    }

    /**
     * 自动验证数据
     * @access protected
     * @param array $data  验证数据
     * @param mixed $rule  验证规则
     * @param bool  $batch 批量验证
     * @return bool
     */
    protected function validateData($data, $rule = null, $batch = null)
    {
        $info = is_null($rule) ? $this->validate : $rule;

        if (!empty($info)) {
            if (is_array($info)) {
                $validate = Facade::make('app')->validate();

                $validate->rule($info['rule']);
                $validate->message($info['msg']);
            } else {
                $name = is_string($info) ? $info : $this->name;

                if (strpos($name, '.')) {
                    list($name, $scene) = explode('.', $name);
                }

                $validate = Facade::make('app')->validate($name);
                if (!empty($scene)) {
                    $validate->scene($scene);
                }
            }
            $batch = is_null($batch) ? $this->batchValidate : $batch;

            if (!$validate->batch($batch)->check($data)) {
                $this->error = $validate->getError();

                if ($this->failException) {
                    throw new ValidateException($this->error);
                } else {
                    return false;
                }
            }
            $this->validate = null;
        }

        return true;
    }

    /**
     * 返回模型的错误信息
     * @access public
     * @return string|array
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access public
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    public function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }
}
