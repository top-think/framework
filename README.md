## shuguo framework
本框架以thinkphp5.1.16为核心而打造的可扩展框架, 扩展composer模块化功能。
例如：http://localhost/sgs-api/api/demo/index

路由解析如下：

项目名: sgs-api；
控制层: demo；
操作层: index

```php

namespaces shuguo/api;

use think\controller;

class DemoController extend controller {
    
    public function index()
    {
        // TODO
        echo 'hello index';
    }
}

```

vendor/chinashuguo/api/src文件结构：
controller/api.php
controller/demo.php
model/
logic/
view/
validate/
service/
common.php
config.php
module.json

## 参与开发

请参阅 [framework 核心框架包](https://gitee.com/chinashuguo/framework)。
