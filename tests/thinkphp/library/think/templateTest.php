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
 * 模板测试
 * @author    oldrind
 */

namespace tests\thinkphp\library\think;

use think\Cache;
use think\Template;

class templateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Template
     */
    protected $template;

    public function setUp()
    {
        $this->template = new Template();
    }

    public function testAssign()
    {
        $reflectProperty = new \ReflectionProperty(get_class($this->template), 'data');
        $reflectProperty->setAccessible(true);

        $this->template->assign('version', 'ThinkPHP3.2');
        $data = $reflectProperty->getValue($this->template);
        $this->assertEquals('ThinkPHP3.2', $data['version']);

        $this->template->assign(['name' => 'Gao', 'version' => 'ThinkPHP5']);
        $data = $reflectProperty->getValue($this->template);
        $this->assertEquals('Gao', $data['name']);
        $this->assertEquals('ThinkPHP5', $data['version']);
    }

    public function testGet()
    {
        $this->template = new Template();
        $data = [
            'project' => 'ThinkPHP',
            'version' => [
                'ThinkPHP5' => ['Think5.0', 'Think5.1']
            ]
        ];
        $this->template->assign($data);

        $this->assertSame($data, $this->template->get());
        $this->assertSame('ThinkPHP', $this->template->get('project'));
        $this->assertSame(['Think5.0', 'Think5.1'], $this->template->get('version.ThinkPHP5'));
        $this->assertNull($this->template->get('version.ThinkPHP3.2'));
    }

    /**
     * @dataProvider provideTestParseWithVar
     */
    public function testParseWithVar($content, $expected)
    {
        $this->template = new Template();

        $this->template->parse($content);
        $this->assertEquals($expected, $content);
    }

    /**
     * @dataProvider provideTestParseWithVarFunction
     */
    public function testParseWithVarFunction($content, $expected)
    {
        $this->template = new Template();

        $this->template->parse($content);
        $this->assertEquals($expected, $content);
    }

    /**
     * @dataProvider provideTestParseWithVarIdentify
     */
    public function testParseWithVarIdentify($content, $expected, $config)
    {
        $this->template = new Template($config);

        $this->template->parse($content);
        $this->assertEquals($expected, $content);
    }

    /**
     * @dataProvider provideTestParseWithThinkVar
     */
    public function testParseWithThinkVar($content, $expected)
    {
        $config['tpl_begin'] = '{';
        $config['tpl_end']   = '}';
        $this->template            = new Template($config);

        $_SERVER['SERVER_NAME'] = 'server_name';
        $_GET['action']         = 'action';
        $_POST['action']        = 'action';
        $_COOKIE['name']        = 'name';
        $_SESSION['action']     = ['name' => 'name'];

        $this->template->parse($content);
        $this->assertEquals($expected, $content);
    }

    /**
     * @expectedException \think\exception\TemplateNotFoundException
     */
    public function testFetchWithEmptyTemplate()
    {
        $this->template = new Template();

        $this->template->fetch('Foo');
    }

    /**
     * @dataProvider provideTestFetchWithNoCache
     */
    public function testFetchWithNoCache($data, $expected)
    {
        $this->template = new Template();

        $this->template->fetch($data['template'], $data['vars'], $data['config']);

        $this->expectOutputString($expected);
    }

    public function testFetchWithCache()
    {
        $this->template = new Template();

        $data = [
            'name' => 'value'
        ];
        $config = [
            'cache_id'      => 'TEST_FETCH_WITH_CACHE',
            'display_cache' => true,
        ];

        $this->template->fetch(APP_PATH . 'views' . DS .'display.html', $data, $config);

        $this->expectOutputString('value');
        $this->assertEquals('value', Cache::get($config['cache_id']));
    }

    public function testDisplay()
    {
        $config = [
            'view_path'   => APP_PATH . DS . 'views' . DS,
            'view_suffix' => '.html',
            'layout_on'   => true,
            'layout_name' => 'layout'
        ];

        $this->template = new Template($config);

        $this->template->assign('files', ['extend' => 'extend', 'include' => 'include']);
        $this->template->assign('user', ['name' => 'name', 'account' => 100]);
        $this->template->assign('message', 'message');
        $this->template->assign('info', ['value' => 'value']);

        $content = <<<EOF
{extend name="\$files.extend" /}
{block name="main"}
main
{block name="side"}
{__BLOCK__}
    {include file="\$files.include" name="\$user.name" value="\$user.account" /}
    {\$message}{literal}{\$message}{/literal}
{/block}
{block name="mainbody"}
    mainbody
{/block}
{/block}
EOF;
        $expected = <<<EOF
<nav>
header
<div id="wrap">
    <input name="info" value="value">
value:

main


    side

    <input name="name" value="100">
value:
    message{\$message}


    mainbody



    {\$name}

    php code</div>
</nav>
EOF;
        $this->template->display($content);
        $this->expectOutputString($expected);
    }

    /**
     * @dataProvider provideTestLayout
     */
    public function testLayout($data, $expected)
    {
        $this->template = new Template();

        $this->template->layout($data['name'], $data['replace']);

        $this->assertSame($expected['layout_on'], $this->template->config('layout_on'));
        $this->assertSame($expected['layout_name'], $this->template->config('layout_name'));
        $this->assertSame($expected['layout_item'], $this->template->config('layout_item'));
    }

    public function testParseAttr()
    {
        $attributes = $this->template->parseAttr("<name version='ThinkPHP' name=\"Gao\"></name>");
        $this->assertSame(['version' => 'ThinkPHP', 'name' => 'Gao'], $attributes);

        $attributes = $this->template->parseAttr("<name version='ThinkPHP' name=\"Gao\">TestCase</name>", 'version');
        $this->assertSame('ThinkPHP', $attributes);
    }

    public function testIsCache()
    {
        $this->template = new Template();
        $config = [
            'cache_id'      => rand(0, 10000) . rand(0, 10000) . time(),
            'display_cache' => true
        ];

        $this->assertFalse($this->template->isCache($config['cache_id']));

        $this->template->fetch(APP_PATH . 'views' . DS .'display.html', [], $config);
        $this->assertTrue($this->template->isCache($config['cache_id']));
    }

    public function provideTestParseWithVar()
    {
        return [
            ["{\$name.a.b}", "<?php echo \$name['a']['b']; ?>"],
            ["{\$name.a??'test'}", "<?php echo isset(\$name['a'])?\$name['a']:'test'; ?>"],
            ["{\$name.a?='test'}", "<?php if(!empty(\$name['a'])) echo 'test'; ?>"],
            ["{\$name.a?:'test'}", "<?php echo !empty(\$name['a'])?\$name['a']:'test'; ?>"],
            ["{\$name.a?\$name.b:'no'}", "<?php echo !empty(\$name['a'])?\$name['b']:'no'; ?>"],
            ["{\$name.a==\$name.b?='test'}", "<?php if(\$name['a']==\$name['b']) echo 'test'; ?>"],
            ["{\$name.a==\$name.b?'a':'b'}", "<?php echo \$name['a']==\$name['b']?'a':'b'; ?>"],
            ["{\$name.a|default='test'==\$name.b?'a':'b'}", "<?php echo (isset(\$name['a']) && (\$name['a'] !== '')?\$name['a']:'test')==\$name['b']?'a':'b'; ?>"],
            ["{\$name.a|trim==\$name.b?='eq'}", "<?php if(trim(\$name['a'])==\$name['b']) echo 'eq'; ?>"],
            ["{:ltrim(rtrim(\$name.a))}", "<?php echo ltrim(rtrim(\$name['a'])); ?>"],
            ["{~echo(trim(\$name.a))}", "<?php echo(trim(\$name['a'])); ?>"],
            ["{++\$name.a}", "<?php echo ++\$name['a']; ?>"],
            ["{/*\$name*/}", ""],
            ["{\$0a}", "{\$0a}"]
        ];
    }

    public function provideTestParseWithVarFunction()
    {
        return [
            ["{\$name.a.b|default='test'}", "<?php echo (isset(\$name['a']['b']) && (\$name['a']['b'] !== '')?\$name['a']['b']:'test'); ?>"],
            ["{\$create_time|date=\"y-m-d\",###}", "<?php echo date(\"y-m-d\",\$create_time); ?>"],
            ["{\$name}\n{\$name|trim|substr=0,3}", "<?php echo \$name; ?>\n<?php echo substr(trim(\$name),0,3); ?>"]
        ];
    }

    public function provideTestParseWithVarIdentify()
    {
        $config['tpl_begin']        = '<#';
        $config['tpl_end']          = '#>';
        $config['tpl_var_identify'] = '';

        return [
            [
                "<#\$info.a??'test'#>",
                "<?php echo ((is_array(\$info)?\$info['a']:\$info->a)) ? (is_array(\$info)?\$info['a']:\$info->a) : 'test'; ?>",
                $config
            ],
            [
                "<#\$info.a?='test'#>",
                "<?php if((is_array(\$info)?\$info['a']:\$info->a)) echo 'test'; ?>",
                $config
            ],
            [
                "<#\$info.a==\$info.b?='test'#>",
                "<?php if((is_array(\$info)?\$info['a']:\$info->a)==(is_array(\$info)?\$info['b']:\$info->b)) echo 'test'; ?>",
                $config
            ],
            [
                "<#\$info.a|default='test'?'yes':'no'#>",
                "<?php echo ((is_array(\$info)?\$info['a']:\$info->a) ?: 'test')?'yes':'no'; ?>",
                $config
            ],
            [
                "{\$info2.b|trim?'yes':'no'}",
                "<?php echo trim(\$info2->b)?'yes':'no'; ?>",
                array_merge(['tpl_var_identify' => 'obj'])
            ]
        ];
    }

    public function provideTestParseWithThinkVar()
    {
        return [
            ["{\$Think.SERVER.SERVER_NAME}<br/>", "<?php echo \\think\\Request::instance()->server('SERVER_NAME'); ?><br/>"],
            ["{\$Think.GET.action}<br/>", "<?php echo \\think\\Request::instance()->get('action'); ?><br/>"],
            ["{\$Think.POST.action}<br/>", "<?php echo \\think\\Request::instance()->post('action'); ?><br/>"],
            ["{\$Think.COOKIE.action}<br/>", "<?php echo \\think\\Cookie::get('action'); ?><br/>"],
            ["{\$Think.COOKIE.action.name}<br/>", "<?php echo \\think\\Cookie::get('action.name'); ?><br/>"],
            ["{\$Think.SESSION.action}<br/>", "<?php echo \\think\\Session::get('action'); ?><br/>"],
            ["{\$Think.SESSION.action.name}<br/>", "<?php echo \\think\\Session::get('action.name'); ?><br/>"],
            ["{\$Think.ENV.OS}<br/>", "<?php echo \\think\\Request::instance()->env('OS'); ?><br/>"],
            ["{\$Think.REQUEST.action}<br/>", "<?php echo \\think\\Request::instance()->request('action'); ?><br/>"],
            ["{\$Think.CONST.THINK_VERSION}<br/>", "<?php echo THINK_VERSION; ?><br/>"],
            ["{\$Think.LANG.action}<br/>", "<?php echo \\think\\Lang::get('action'); ?><br/>"],
            ["{\$Think.CONFIG.action.name}<br/>", "<?php echo \\think\\Config::get('action.name'); ?><br/>"],
            ["{\$Think.NOW}<br/>", "<?php echo date('Y-m-d g:i a',time()); ?><br/>"],
            ["{\$Think.VERSION}<br/>", "<?php echo THINK_VERSION; ?><br/>"],
            ["{\$Think.LDELIM}<br/>", "<?php echo '{'; ?><br/>"],
            ["{\$Think.RDELIM}<br/>", "<?php echo '}'; ?><br/>"],
            ["{\$Think.THINK_VERSION}<br/>", "<?php echo THINK_VERSION; ?><br/>"],
            ["{\$Think.SITE.URL}", "<?php echo ''; ?>"]
        ];
    }

    public function provideTestFetchWithNoCache()
    {
        $provideData = [];

        $this->template = [
            'template' => APP_PATH . 'views' . DS .'display.html',
            'vars'     => [],
            'config'   => []
        ];
        $expected = 'default';
        $provideData[] = [$this->template, $expected];

        $this->template = [
            'template' => APP_PATH . 'views' . DS .'display.html',
            'vars'     => ['name' => 'ThinkPHP5'],
            'config'   => []
        ];
        $expected = 'ThinkPHP5';
        $provideData[] = [$this->template, $expected];

        $this->template = [
            'template' => 'views@display',
            'vars'     => [],
            'config'   => [
                'view_suffix' => 'html'
            ]
        ];
        $expected = 'default';
        $provideData[] = [$this->template, $expected];

        $this->template = [
            'template' => 'views@/display',
            'vars'     => ['name' => 'ThinkPHP5'],
            'config'   => [
                'view_suffix' => 'phtml'
            ]
        ];
        $expected = 'ThinkPHP5';
        $provideData[] = [$this->template, $expected];

        $this->template = [
            'template' => 'display',
            'vars'     => ['name' => 'ThinkPHP5'],
            'config'   => [
                'view_suffix' => 'html',
                'view_base'   => APP_PATH . 'views' . DS
            ]
        ];
        $expected = 'ThinkPHP5';
        $provideData[] = [$this->template, $expected];

        return $provideData;
    }

    public function provideTestLayout()
    {
        $provideData = [];

        $data = ['name' => false, 'replace' => ''];
        $expected = ['layout_on' => false, 'layout_name' => 'layout', 'layout_item' => '{__CONTENT__}'];
        $provideData[] = [$data, $expected];

        $data = ['name' => null, 'replace' => ''];
        $expected = ['layout_on' => true, 'layout_name' => 'layout', 'layout_item' => '{__CONTENT__}'];
        $provideData[] = [$data, $expected];

        $data = ['name' => 'ThinkName', 'replace' => 'ThinkReplace'];
        $expected = ['layout_on' => true, 'layout_name' => 'ThinkName', 'layout_item' => 'ThinkReplace'];
        $provideData[] = [$data, $expected];

        return $provideData;
    }
}
