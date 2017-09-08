<?php
namespace tests\thinkphp\library\traits\think;

use traits\think\Instance;

class instanceTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $father = InstanceTestFather::instance();
        $this->assertInstanceOf('\tests\thinkphp\library\traits\think\InstanceTestFather', $father);
        $this->assertEquals([], $father->options);

        $father2 = InstanceTestFather::instance(['father']);
        $this->assertEquals([], $father2->options);

        $father2->options = ['father'];
        $this->assertEquals(['father'], $father->options);

        $son = InstanceTestSon::instance(['son']);
        $this->assertInstanceOf('\tests\thinkphp\library\traits\think\InstanceTestFather', $son);
        $this->assertEquals(['father'], $son->options);
    }

    public function testCallStatic()
    {
        $father = InstanceTestFather::instance();
        $this->assertEquals([], $father->options);

        $this->assertEquals($father::__protectedStaticFunc(['thinkphp']), 'protectedStaticFunc["thinkphp"]');

        try {
            $father::_protectedStaticFunc();
            $this->setExpectedException('\think\Exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\think\Exception', $e);
        }
    }

    protected function tearDown()
    {
        call_user_func(\Closure::bind(function () {
            InstanceTestFather::$instance = null;
        }, null, '\tests\thinkphp\library\traits\think\InstanceTestFather'));
    }
}

class InstanceTestFather
{
    use Instance;

    public $options = null;

    public function __construct($options)
    {
        $this->options = $options;
    }

    protected static function _protectedStaticFunc($params)
    {
        return 'protectedStaticFunc' . json_encode($params);
    }
}

class InstanceTestSon extends InstanceTestFather
{
}
