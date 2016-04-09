<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * Db类测试
 */
namespace tests\thinkphp\library\think;

use think\Db;
use think\Config;

class dbTest extends \PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        // $this->object = new Session ();
        // register_shutdown_function ( function () {
        // } ); // 此功能无法取消，需要回调函数配合。
        set_exception_handler(function () {
        });
        set_error_handler(function () {
        });
    }

    protected function tearDown()
    {
        register_shutdown_function('think\Error::appShutdown');
        set_error_handler('think\Error::appError');
        set_exception_handler('think\Error::appException');
    }

    /**
     * @covers think\Db::parseDsn
     */
    public function testParseDsn()
    {

        $method = new \ReflectionMethod('think\Db', 'parseDsn');
        $method->setAccessible(true);//设为可访问


        //验证空处理
        $res = $method->invoke(null, "");
        $this->assertTrue(is_array($res));
        $this->assertTrue(empty($res[0]));


        //验证所有参数
        $res = $method->invoke(null, "mysql://root:dbpassword@127.0.0.1:8888/test?arg1=argval1&arg2=argval2#utf8");


        $this->assertEquals($res["type"], "mysql");
        $this->assertEquals($res["username"], "root");
        $this->assertEquals($res["password"], "dbpassword");
        $this->assertEquals($res["hostname"], "127.0.0.1");
        $this->assertEquals($res["hostport"], "8888");
        $this->assertEquals($res["database"], "test");
        $this->assertEquals($res["charset"], "utf8");
        $this->assertEquals($res["params"]["arg1"], "argval1");
        $this->assertEquals($res["params"]["arg2"], "argval2");


        //验证无params
        $res = $method->invoke(null, "mysql://root:dbpassword@127.0.0.1:8888/test#utf8");
        $this->assertTrue(is_array($res["params"]));
        $this->assertTrue(empty($res["params"][0]));


    }


    /**
     * @covers think\Db::parseConfig
     */
    public function testParseConfig()
    {
        $method = new \ReflectionMethod('think\Db', 'parseConfig');
        $method->setAccessible(true);//设为可访问

        //有配置
        $config = "mysql://root:hasdbpassword@127.0.0.1:8888/test?arg1=argval1&arg2=argval2#utf8";

        $res = $method->invoke(null, $config);

        $this->assertEquals($res["password"], "hasdbpassword");


        //读取配置
        $configkey = "dbtestconfig";
        $databaseConf = array(
            // 数据库类型
            'type' => 'mysql',
            // 数据库连接DSN配置
            'dsn' => '',
            // 服务器地址
            'hostname' => '127.0.0.1',
            // 数据库名
            'database' => '',
            // 数据库用户名
            'username' => 'root',
            // 数据库密码
            'password' => 'dbtestconfigpassword',
            // 数据库连接端口
            'hostport' => '',
            // 数据库连接参数
            'params' => [],
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => '',
            // 数据库调试模式
            'debug' => APP_DEBUG,
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy' => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate' => false,
            // 读写分离后 主服务器数量
            'master_num' => 1,
            // 指定从服务器序号
            'slave_no' => ''
        );


        Config::set("dbtestconfig", $databaseConf);

        $res = $method->invoke(null, $configkey);

        $this->assertEquals("dbtestconfigpassword", $res["password"]);

        //无配置

        $databaseConf = array(
            // 数据库类型
            'type' => 'mysql',
            // 数据库连接DSN配置
            'dsn' => '',
            // 服务器地址
            'hostname' => '127.0.0.1',
            // 数据库名
            'database' => '',
            // 数据库用户名
            'username' => 'root',
            // 数据库密码
            'password' => 'noconf',
            // 数据库连接端口
            'hostport' => '',
            // 数据库连接参数
            'params' => [],
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => '',
            // 数据库调试模式
            'debug' => APP_DEBUG,
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy' => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate' => false,
            // 读写分离后 主服务器数量
            'master_num' => 1,
            // 指定从服务器序号
            'slave_no' => ''
        );
        Config::set('database', $databaseConf);

        $res = $method->invoke(null, "");

        $this->assertEquals("noconf", $res["password"]);

        // mock Config::get
    }

    /**
     * @covers think\Db::connect
     * @expectedException \think\Exception
     */
    public function testConnectException()
    {
        $this->setExpectedException('\think\Exception', 'db type error');
        Db::connect('xxxx');

    }

    /**
     * @covers think\Db::connect
     *
     */
    public function testConnect()
    {

        $db = Db::connect('mysql://root@127.0.0.1/test#utf8');
        $this->assertEquals(get_class($db), "think\\db\\driver\\Mysql");

    }


    /**
     * @covers think\Db::__callStatic
     *
     */
    public function test__callStatic()
    {

        $db = Db::name("tablename");
        $this->assertEquals(get_class($db), "think\\db\\driver\\Mysql");
    }


}
