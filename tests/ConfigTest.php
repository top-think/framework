<?php

namespace think\tests;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use think\Config;

class ConfigTest extends TestCase
{
    public function testLoad()
    {
        $root = vfsStream::setup();
        $file = vfsStream::newFile('test.php')->setContent("<?php return ['key1'=> 'value1','key2'=>'value2'];");
        $root->addChild($file);

        $config = new Config();

        $config->load($file->url(), 'test');

        $this->assertEquals('value1', $config->get('test.key1'));
        $this->assertEquals('value2', $config->get('test.key2'));

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $config->get('test'));
    }

    public function testSetAndGet()
    {
        $config = new Config();

        $config->set([
            'key1' => 'value1',
            'key2' => [
                'key3' => 'value3',
            ],
        ], 'test');

        $this->assertTrue($config->has('test.key1'));
        $this->assertEquals('value1', $config->get('test.key1'));
        $this->assertEquals('value3', $config->get('test.key2.key3'));

        $this->assertEquals(['key3' => 'value3'], $config->get('test.key2'));
        $this->assertFalse($config->has('test.key3'));
        $this->assertEquals('none', $config->get('test.key3', 'none'));
    }

    public function testHook()
    {
        $config = new Config();

        $config->set([
            'key1' => 'value1',
            'key2' => [
                'key3' => 'value3',
            ],
        ], 'test');

        $config->set([
            'key1' => 'value1',
            'key2' => [
                'key3' => 'value3',
            ],
        ], 'test2');

        $config->set([
            'key1' => 'value1',
            'key2' => [
                'key3' => 'value3',
            ],
        ], 'test3');

        $config->hook(function ($name, $value) {
            if ($name == 'test.key1') {
                return 'hook1';
            } else {
                return $value;
            }
        }, 'test');

        $config->hook(function ($name, $value) {
            if ($name == 'test2.key1') {
                return 'hook2';
            } else {
                return $value;
            }
        }, 'test2');
        
        // 测试默认hook $key = global
        $config->hook(function ($name, $value) {
            if ($name == 'test3.key1.key3') {
                return 'hook3';
            } else {
                return $value;
            }
        });

        $this->assertEquals('hook1', $config->get('test.key1'));
        $this->assertEquals('hook2', $config->get('test2.key1'));
        $this->assertEquals('hook3', $config->get('test3.key2.key3'));
    }
}
