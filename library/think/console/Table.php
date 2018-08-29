<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\console;

class Table
{
    protected $output;

    protected $header = [];

    protected $rows = [];

    protected $colWidth = [];

    protected $style  = 'default';
    protected $format = [
        'default'    => [
            'top'    => ['+', '-', '+', '+'],
            'cell'   => ['|', ' ', '|', '|'],
            'middle' => ['+', '-', '+', '+'],
            'bottom' => ['+', '-', '+', '+'],
        ],
        'box'        => [
            'top'    => ['┌', '─', '┬', '┐'],
            'cell'   => ['│', ' ', '│', '│'],
            'middle' => ['├', '─', '┼', '┤'],
            'bottom' => ['└', '─', '┴', '┘'],
        ],
        'box-double' => [
            'top'    => ['╔', '═', '╤', '╗'],
            'cell'   => ['║', ' ', '│', '║'],
            'middle' => ['╠', '─', '╪', '╣'],
            'bottom' => ['╚', '═', '╧', '╝'],
        ],
    ];

    /**
     * 构造方法
     * @param string|null $name 命令名称,如果没有设置则比如在 configure() 里设置
     * @throws \LogicException
     * @api
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    public function setHeader(array $header)
    {
        $this->header = $header;
        foreach ($header as $key => $val) {
            $this->colWidth[$key] = strlen($val);
        }
    }

    public function setRows(array $rows)
    {
        $this->rows = $rows;

        foreach ($rows as $row) {
            foreach ((array) $row as $key => $cell) {
                if (strlen($cell) > $this->colWidth[$key]) {
                    $this->colWidth[$key] = strlen($cell);
                }
            }
        }
    }

    public function setStyle($style)
    {
        $this->style = $style;
    }

    protected function renderSeparator($pos)
    {
        $style = $this->format[$this->style][$pos];
        $array = [];

        foreach ($this->header as $key => $val) {
            $array[] = str_repeat($style[1], $this->colWidth[$key] + 2);
        }

        $content = $style[0] . implode($style[2], $array) . $style[3] . PHP_EOL;
        return $content;
    }

    protected function renderHeader()
    {
        $style = $this->format[$this->style]['cell'];
        $array = [];

        foreach ($this->header as $key => $header) {
            $array[] = ' ' . str_pad($header, $this->colWidth[$key], $style[1]);
        }

        $content = $this->renderSeparator('top');
        $content .= $style[0] . implode(' ' . $style[2], $array) . ' ' . $style[3] . PHP_EOL;
        $content .= $this->renderSeparator('middle');

        return $content;
    }

    public function render()
    {
        // 输出头部
        $content = $this->renderHeader();
        $style   = $this->format[$this->style]['cell'];

        foreach ($this->rows as $row) {
            if (is_string($row) && false !== strpos($row, '-')) {
                $content .= $this->renderSeparator('middle');
            } elseif (is_scalar($row)) {
                $array = ' ' . str_pad($row, array_reduce($this->colWidth, function ($a, $b) {
                    return $a + $b;
                })) . str_repeat(' ', 3 * (count($this->colWidth) - 1)) . " ";

                $content .= $style[0] . $array . $style[3] . PHP_EOL;
            } else {

                $array = [];
                foreach ($row as $key => $val) {
                    $array[] = ' ' . str_pad($val, $this->colWidth[$key]);
                }

                $content .= $style[0] . implode(' ' . $style[2], $array) . ' ' . $style[3] . PHP_EOL;
            }
        }

        $content .= $this->renderSeparator('bottom');

        $this->output->writeln($content);
    }
}
