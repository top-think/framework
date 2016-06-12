<?php

namespace think\log\driver;
use think\Request;
/**
 * 模拟测试输出
 */
class Browser
{
    protected $config = [
        'notview_save' => 'File',
    ];

    // 实例化并传入参数
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 日志写入接口
     * @access public
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log = [])
    {
        $request = Request::instance();
        $type = $request->type();
        $type = config('default_return_type');
        //输出到控制台
        if(in_array($type, ['html', 'txt'])){
            $lines = [];
            foreach ($log as $key => $l) {
                $lines[] = $this->output($l['type'], $l['msg']);
            }
            $lines = implode(PHP_EOL, $lines);
            $js = <<<JS

<script>
{$lines}
</script>
JS;
            echo $js;
        }else{
            $other_save = $this->config['notview_save'];
            // $other_save = "\think\log\driver\"".$other_save;
            $other_save = 'think\log\driver\\'.$other_save;
            $other_save_class = new $other_save();
            $other_save_class->save($log);
        }
        return true;
    }

    public function output($type, $msg){
        // dump($type);
        // dump($msg);
        $msg = str_replace(PHP_EOL, '\n', $msg);
        if(in_array($type, ['info', 'log', 'error', 'warn', 'debug'])){
            $style = '';
            if('error' == $type){
                $style = 'color:#F4006B;font-size:14px;';
            }
            $line = "console.{$type}(\"%c{$msg}\", \"{$style}\");";
        }else{
            if('sql' == $type){
                $style = "color:#009bb4;";
                $line = "console.log(\"%c{$msg}\", \"{$style}\");";
            }else{
                $line = "alert(\"{$msg}\");";
            }
        }
        return $line;
    }

}
