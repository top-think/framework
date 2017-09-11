<?php
namespace tests\thinkphp\library\traits\model;

use think\Db;
use think\Model;
use traits\model\SoftDelete;

class softDeleteTest extends \PHPUnit_Framework_TestCase
{
    const TEST_TIME = 10000;

    public function setUp()
    {
        $config = (new testClassWithSoftDelete())->connection;

        $sql[] = <<<SQL
DROP TABLE IF EXISTS `tp_soft_delete`;
SQL;

        $sql[] = <<<SQL
CREATE TABLE `tp_soft_delete` (
  `id` int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` char(40) NOT NULL DEFAULT '' COMMENT '用户名',
  `delete_time` int(10) DEFAULT NULL COMMENT '软删除时间'
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='ThinkPHP SoftDelete Test';
SQL;

        $time = self::TEST_TIME;
        $sql[] = "INSERT INTO tp_soft_delete (`id`, `name`, `delete_time`) VALUES (1, 'valid data1', null)";
        $sql[] = "INSERT INTO tp_soft_delete (`id`, `name`, `delete_time`) VALUES (2, 'invalid data2', {$time})";
        $sql[] = "INSERT INTO tp_soft_delete (`id`, `name`, `delete_time`) VALUES (3, 'invalid data3', {$time})";
        $sql[] = "INSERT INTO tp_soft_delete (`id`, `name`, `delete_time`) VALUES (4, 'valid data4', null)";
        $sql[] = "INSERT INTO tp_soft_delete (`id`, `name`, `delete_time`) VALUES (5, 'valid data5', null)";

        foreach ($sql as $one) {
            Db::connect($config)->execute($one);
        }
    }

    public function testTrashed()
    {
        /** @var testClassWithSoftDelete[] $selections */
        $selections = testClassWithSoftDelete::withTrashed()->select();

        $this->assertFalse($selections[0]->trashed());
        $this->assertTrue($selections[1]->trashed());
        $this->assertTrue($selections[2]->trashed());
    }

    public function testDefaultTrashed()
    {
        $this->assertCount(3, testClassWithSoftDelete::all());
    }

    public function testWithTrashed()
    {
        $this->assertCount(5, testClassWithSoftDelete::withTrashed()->select());
    }

    public function testOnlyTrashed()
    {
        $this->assertCount(2, testClassWithSoftDelete::onlyTrashed()->select());
    }

    public function testSoftDelete()
    {
        $this->assertEquals(1, testClassWithSoftDelete::get(1)->delete());
        $this->assertNotNull(testClassWithSoftDelete::withTrashed()->find(1)->getData('delete_time'));
    }

    public function testForceDelete()
    {
        $this->assertEquals(1, testClassWithSoftDelete::get(1)->delete(true));
        $this->assertNull(testClassWithSoftDelete::get(1));
    }

    public function testSoftDestroy()
    {
        $this->assertEquals(5, testClassWithSoftDelete::destroy([1, 2, 3, 4, 5, 6]));
        $this->assertNotNull(testClassWithSoftDelete::withTrashed()->find(2)->getData('delete_time'));
        $this->assertNotEquals(self::TEST_TIME, testClassWithSoftDelete::withTrashed()->find(2)->getData('delete_time'));
        $this->assertNotEquals(self::TEST_TIME, testClassWithSoftDelete::withTrashed()->find(3)->getData('delete_time'));
        $this->assertNotNull(testClassWithSoftDelete::withTrashed()->find(4)->getData('delete_time'));
        $this->assertNotNull(testClassWithSoftDelete::withTrashed()->find(5)->getData('delete_time'));
    }

    public function testForceDestroy()
    {
        $this->assertEquals(5, testClassWithSoftDelete::destroy([1, 2, 3, 4, 5, 6], true));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(1));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(2));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(3));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(4));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(5));
    }

    public function testRestore()
    {
        /** @var testClassWithSoftDelete[] $selections */
        $selections = testClassWithSoftDelete::withTrashed()->select();

        $this->assertEquals(0, $selections[0]->restore());
        $this->assertEquals(1, $selections[1]->restore());
        $this->assertEquals(1, $selections[2]->restore());
        $this->assertEquals(0, $selections[3]->restore());
        $this->assertEquals(0, $selections[4]->restore());

        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(1)->getData('delete_time'));
        $this->assertNull(testClassWithSoftDelete::withTrashed()->find(2)->getData('delete_time'));
    }

    public function testGetDeleteTimeField()
    {
        $testClass = new testClassWithSoftDelete();

        $this->assertEquals('delete_time', $testClass->getDeleteTimeField());

        $testClass->deleteTime = 'create_time';
        $this->assertEquals('create_time', $testClass->getDeleteTimeField());

        $testClass->deleteTime = 'test.create_time';
        $this->assertEquals('create_time', $testClass->getDeleteTimeField());

        $testClass->deleteTime = 'create_time';
        $this->assertEquals('__TABLE__.create_time', $testClass->getDeleteTimeField(true));
    }
}

class testClassWithSoftDelete extends Model
{
    public $table = 'tp_soft_delete';

    public $deleteTime = 'delete_time';

    public $connection = [
        // 数据库类型
        'type'           => 'mysql',
        // 服务器地址
        'hostname'       => '127.0.0.1',
        // 数据库名
        'database'       => 'test',
        // 用户名
        'username'       => 'root',
        // 密码
        'password'       => '',
        // 端口
        'hostport'       => '',
        // 连接dsn
        'dsn'            => '',
        // 数据库连接参数
        'params'         => [],
        // 数据库编码默认采用utf8
        'charset'        => 'utf8',
        // 数据库表前缀
        'prefix'         => '',
        // 数据库调试模式
        'debug'          => true,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'         => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'    => false,
        // 读写分离后 主服务器数量
        'master_num'     => 1,
        // 指定从服务器序号
        'slave_no'       => '',
        // 是否严格检查字段是否存在
        'fields_strict'  => true,
        // 数据集返回类型 array 数组 collection Collection对象
        'resultset_type' => 'array',
        // 是否自动写入时间戳字段
        'auto_timestamp' => false,
        // 是否需要进行SQL性能分析
        'sql_explain'    => false,
    ];

    use SoftDelete {
        getDeleteTimeField as public;
    }
}
