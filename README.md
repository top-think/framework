ThinkPHP 5.1Beta
===============

ThinkPHP5.1对底层架构做了进一步的改进，减少依赖，其主要特性包括：

 + 采用容器统一管理对象
 + 支持Facade
 + 更易用的路由
 + 配置和路由目录独立
 + 取消系统常量
 + 助手函数增强
 + 类库别名机制
 + 模型和数据库增强
 + 依赖注入完善
 + 支持配置多级获取

废除的功能：

 + 聚合模型
 + 内置控制器扩展类

> ThinkPHP5的运行环境要求PHP5.6以上。


## 目录结构

初始的目录结构如下：

~~~
www  WEB部署目录（或者子目录）
├─application           应用目录
│  ├─common             公共模块目录（可以更改）
│  ├─module_name        模块目录
│  │  ├─common.php      模块函数文件
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  ├─view            视图目录
│  │  └─ ...            更多类库目录
│  │
│  ├─command.php        命令行定义文件
│  ├─common.php         公共函数文件
│  └─tags.php           应用行为扩展定义文件
│
├─config                应用配置目录
│  ├─module_name        模块配置目录
│  │  ├─database.php    数据库配置
│  │  ├─cache           缓存配置
│  │  └─ ...            
│  │
│  ├─app.php            应用配置
│  ├─cache.php          缓存配置
│  ├─cookie.php         Cookie配置
│  ├─database.php       数据库配置
│  ├─log.php            日志配置
│  ├─session.php        Session配置
│  ├─template.php       模板引擎配置
│  └─trace.php          Trace配置
│
├─route                 路由定义目录
│  ├─route.php          路由定义
│  └─...                更多
│
├─public                WEB目录（对外访问目录）
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─thinkphp              框架系统目录
│  ├─lang               语言文件目录
│  ├─library            框架类库目录
│  │  ├─think           Think类库包目录
│  │  └─traits          系统Trait目录
│  │
│  ├─tpl                系统模板目录
│  ├─base.php           基础定义文件
│  ├─console.php        控制台入口文件
│  ├─convention.php     框架惯例配置文件
│  ├─helper.php         助手函数文件
│  ├─phpunit.xml        phpunit配置文件
│  └─start.php          框架入口文件
│
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件
~~~

> router.php用于php自带webserver支持，可用于快速测试
> 切换到public目录后，启动命令：php -S localhost:8888  router.php
> 上面的目录结构和名称是可以改变的，这取决于你的入口文件和配置参数。

## 升级指导

应用类库的命名空间app如果需要更改，设置app_namespace环境变量

取消命名空间的别名功能，原有下面系统类库的命名空间需要调整：

* think\App      => think\facade\App （或者 App ）
* think\Cache    => think\facade\Cache （或者 Cache ）
* think\Config   => think\facade\Config （或者 Config ）
* think\Cookie   => think\facade\Cookie （或者 Cookie ）
* think\Debug    => think\facade\Debug （或者 Debug ）
* think\Env      => think\facade\Env （或者 Env ）
* think\Hook     => think\facade\Hook （或者 Hook ）
* think\Lang     => think\facade\Lang （或者 Lang ）
* think\Log      => think\facade\Log （或者 Log ）
* think\Request  => think\facade\Request （或者 Request ）
* think\Response => think\facade\Reponse （或者 Reponse ）
* think\Route    => think\facade\Route （或者 Route ）
* think\Session  => think\facade\Session （或者 Session ）
* think\Url      => think\facade\Url （或者 Url ）
* think\View     => think\facade\View （或者 View ）

原有的配置文件config.php 拆分为app.php cache.php 等独立配置文件 放入config目录（原来模块的配置目录直接移动到config目录下面）。
原有的路由定义文件route.php 移动到route目录（支持放置任意文件名的路由定义文件）

取消Loader::import方法以及import和vendor助手函数
原来Loader类的controller、model、action和validate方法改为App类的同名方法
模型的数据集查询始终返回数据集对象而不是数组
模型的数据表主键如果不是id 需要设置模型的pk属性
路由的before_behavior和after_behavior参数更改为before和after
路由缓存功能暂时取消
软删除trait引入更改为 think\model\concern\SoftDelete

## 命名规范

`ThinkPHP5`遵循PSR-2命名规范和PSR-4自动加载规范。

## 参与开发
请参阅 [ThinkPHP5 核心框架包](https://github.com/top-think/framework)。

## 版权信息

ThinkPHP遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2006-2017 by ThinkPHP (http://thinkphp.cn)

All rights reserved。

ThinkPHP® 商标和著作权所有者为上海顶想信息科技有限公司。

更多细节参阅 [LICENSE.txt](LICENSE.txt)
