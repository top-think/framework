## shuguo framework
本框架以thinkphp5.1.16为核心而打造的可扩展框架, 扩展composer模块化功能, 最新版本为v5.1.24。
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
config/
command/
model/
logic/
view/
validate/
service/
common.php
command.php
module.json 

ThinkPHP 5.1.24 —— 12载初心，你值得信赖的PHP框架
===============

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/top-think/framework/badges/quality-score.png?b=5.1)](https://scrutinizer-ci.com/g/top-think/framework/?branch=5.1)
[![Build Status](https://travis-ci.org/top-think/framework.svg?branch=master)](https://travis-ci.org/top-think/framework)
[![Total Downloads](https://poser.pugx.org/topthink/framework/downloads)](https://packagist.org/packages/topthink/framework)
[![Latest Stable Version](https://poser.pugx.org/topthink/framework/v/stable)](https://packagist.org/packages/topthink/framework)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D5.6-8892BF.svg)](http://www.php.net/)
[![License](https://poser.pugx.org/topthink/framework/license)](https://packagist.org/packages/topthink/framework)

ThinkPHP5.1.24对底层架构做了进一步的改进，减少依赖，其主要特性包括：

 + 采用容器统一管理对象
 + 支持Facade
 + 更易用的路由
 + 注解路由支持
 + 路由跨域请求支持
 + 验证类增强
 + 配置和路由目录独立
 + 取消系统常量
 + 类库别名机制
 + 模型和数据库增强
 + 依赖注入完善
 + 支持PSR-3日志规范
 + 中间件支持（`V5.1.6+`）
 + 支持`Swoole`/`Workerman`运行（`V5.1.18+`）
 + 支持命令模块化
 + 支持语言模块化
 + 支持基本加密解密算法

### 废除的功能：

 + 聚合模型
 + 内置控制器扩展类
 + 模型自动验证

> ThinkPHP5.1.24的运行环境要求PHP5.6+。

## 安装

使用composer安装

~~~
composer create-project topthink/think tp
~~~

启动服务

~~~
cd tp
php think run
~~~

然后就可以在浏览器中访问

~~~
http://localhost:8000
~~~

更新框架
~~~
composer update topthink/framework
~~~


## 在线手册

+ [完全开发手册](https://www.kancloud.cn/manual/thinkphp5_1/content)
+ [升级指导](https://www.kancloud.cn/manual/thinkphp5_1/354155) 

## 命名规范

`ThinkPHP5.1.24`遵循PSR-2命名规范和PSR-4自动加载规范。

## 参与开发

请参阅 [framework 核心框架包](https://gitee.com/chinashuguo/framework)。
