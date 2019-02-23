![](https://box.kancloud.cn/5a0aaa69a5ff42657b5c4715f3d49221) 

ThinkPHP 5.2
===============

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/top-think/framework/badges/quality-score.png?b=5.2)](https://scrutinizer-ci.com/g/top-think/framework/?branch=5.2)
[![Build Status](https://travis-ci.org/top-think/framework.svg?branch=master)](https://travis-ci.org/top-think/framework)
[![Total Downloads](https://poser.pugx.org/topthink/framework/downloads)](https://packagist.org/packages/topthink/framework)
[![Latest Stable Version](https://poser.pugx.org/topthink/framework/v/stable)](https://packagist.org/packages/topthink/framework)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.1-8892BF.svg)](http://www.php.net/)
[![License](https://poser.pugx.org/topthink/framework/license)](https://packagist.org/packages/topthink/framework)

ThinkPHP5.2底层架构采用PHP7.1改写和进一步优化。

### 计划实现的核心主要特性：

 + 采用PHP7强类型特性
 + 支持更多的PSR规范
 + 多应用支持
 + 引入事件系统替代行为
 + 视图和模板引擎分离
 + IDE助手
 + 用法统一和精简


> ThinkPHP5.2的运行环境要求PHP7.1+。

## 安装

~~~
composer create-project topthink/think tp5 5.2.*-dev
~~~

启动服务

~~~
cd tp5
php think run
~~~

然后就可以在浏览器中访问

~~~
http://localhost:8000
~~~

如果需要更新框架使用
~~~
composer update topthink/framework
~~~

## 命名规范

`ThinkPHP5`遵循PSR-2命名规范和PSR-4自动加载规范。

## 参与开发

请参阅 [ThinkPHP5 核心框架包](https://github.com/top-think/framework)。

## 版权信息

ThinkPHP遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2006-2018 by ThinkPHP (http://thinkphp.cn)

All rights reserved。

ThinkPHP® 商标和著作权所有者为上海顶想信息科技有限公司。

更多细节参阅 [LICENSE.txt](LICENSE.txt)
