<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Axios <axioscros@aliyun.com>
// +----------------------------------------------------------------------
// | Datetime: 2017/5/5 14:22
// +----------------------------------------------------------------------

namespace think\log\driver;
use think\App;
use think\Config;
use think\Db;

class Mongodb{
    protected $config = [
        // 日志保存目录
        'path'  => LOG_PATH,
        //MongoDB的连接配置
        'connection'=>'default',
        //日志数据库名称
        'database'=>'log',
        //日志时间日期格式
        'time_format'=>"Y-m-d H:i:s",
        //独立记录的日志级别
        'apart_level' => [],
    ];

    protected $mongo_config;

    protected $database;

    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
        $this->mongo_config = Config::get("mongo.".$this->config['connection']);
        if(empty($this->mongo_config)){
            throw new \InvalidArgumentException('mongodb config not exits');
        }
        $this->database     = $this->config['database'];
    }

    /**
     * 日志写入接口
     * @access public
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log = []){
        $insert = [];
        $timestamp = time();
        $datetime = isset($this->config['time_format'])?date($this->config['time_format']):date("Y-m-d H:i:s");

        if (App::$debug ) {
            if(isset($_SERVER['HTTP_HOST'])){
                $insert['current_url'] =  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            } else {
                $insert['current_url'] = "cmd:" . implode(' ', $_SERVER['argv']);
            }

            $runtime    = round(microtime(true) - THINK_START_TIME, 10);
            $qps        = $runtime > 0 ? number_format(1 / $runtime, 2). 'req/s]' : '∞'. 'req/s]';
            $runtime_str=  number_format($runtime, 6) . 's';
            $memory_use = number_format((memory_get_usage() - THINK_START_MEM) / 1024, 2);
            $file_load  = count(get_included_files());
            $server     = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '0.0.0.0';
            $remote     = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
            $method     = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
            $insert     = [
                'timestamp'=>$timestamp,
                'datetime'=>$datetime,
                'method'=>$method,
                'runtime'=>$runtime_str,
                'qps'=>$qps,
                'memory_use'=>$memory_use . 'kb',
                'file_load'=>$file_load,
                'server'=>$server,
                'remote'=>$remote
            ];
        }

        $content=[];
        foreach ($log as $type => $val){
            if(isset($content[$type])){
                $n = count($val);
            }else{
                $n=0;
            }
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $content[$type][$n] = $msg;
                $n++;
            }
            if(in_array($type, $this->config['apart_level'])){
                $this->log($content,$type);
            }
        }
        $insert['log'] = $content;
        $this->log($insert);
        return true;
    }

    protected function log($insert=[],$database=''){
        if(empty($database)){
            $database = $this->database;
        }
        $mongo = Db::connect($this->mongo_config)->name($database);
        if(empty($mongo)){
            throw new \InvalidArgumentException('mongodb connection fail');
        }
        return $mongo->insert($insert);
    }
}