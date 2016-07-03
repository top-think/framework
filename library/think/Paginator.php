<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think;

use think\paginator\Collection as PaginatorCollection;
use think\Request;

abstract class Paginator
{
    /** @var bool 是否为简洁模式 */
    protected $simple = false;

    /** @var PaginatorCollection 数据集 */
    protected $items;

    /** @var integer 当前页 */
    protected $currentPage;

    /** @var  integer 最后一页 */
    protected $lastPage;

    /** @var integer|null 数据总数 */
    protected $total;

    /** @var  integer 每页的数量 */
    protected $listRows;

    /** @var bool 是否有下一页 */
    protected $hasMore;

    /** @var array 一些配置 */
    protected $options = [
        'var_page' => 'page',
        'path'     => '/',
        'query'    => [],
        'fragment' => ''
    ];

    public function __construct($items, $listRows, $currentPage = null, $simple = false, $total = null, $options = [])
    {
        $this->options = array_merge($this->options, $options);

        $this->options['path'] = $this->options['path'] != '/' ? rtrim($this->options['path'], '/') : $this->options['path'];

        $this->simple   = $simple;
        $this->listRows = $listRows;

        if ($simple) {
            if (!$items instanceof Collection) {
                $items = Collection::make($items);
            }
            $this->currentPage = $this->setCurrentPage($currentPage);
            $this->hasMore     = count($items) > ($this->listRows);
            $items             = $items->slice(0, $this->listRows);
        } else {
            $this->total       = $total;
            $this->lastPage    = (int)ceil($total / $listRows);
            $this->currentPage = $this->setCurrentPage($currentPage);
            $this->hasMore     = $this->currentPage < $this->lastPage;
        }

        $this->items = PaginatorCollection::make($items, $this);
    }

    public function items()
    {
        return $this->items;
    }


    protected function setCurrentPage($currentPage)
    {
        if (!$this->simple && $currentPage > $this->lastPage) {
            return $this->lastPage > 0 ? $this->lastPage : 1;
        }

        return $currentPage;
    }

    /**
     * 获取页码对应的链接
     *
     * @param $page
     * @return string
     */
    protected function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        if (strpos($this->options['path'], '[PAGE]') === false) {
            $parameters = [$this->options['var_page'] => $page];
            $path       = $this->options['path'];
        } else {
            $parameters = [];
            $path       = str_replace('[PAGE]', $page, $this->options['path']);
        }
        if (count($this->options['query']) > 0) {
            $parameters = array_merge($this->options['query'], $parameters);
        }
        $url = $path;
        if (!empty($parameters)) {
            $url .= '?' . urldecode(http_build_query($parameters, null, '&'));
        }
        return $url . $this->buildFragment();
    }

    /**
     * 自动获取当前页码
     * @param string $varPage
     * @param int    $default
     * @return int
     */
    public static function getCurrentPage($varPage = 'page', $default = 1)
    {
        $page = Request::instance()->request($varPage);

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int)$page >= 1) {
            return $page;
        }

        return $default;
    }

    /**
     * 自动获取当前的path
     * @return string
     */
    public static function getCurrentPath()
    {
        return Request::instance()->baseUrl();
    }

    public function total()
    {
        if ($this->simple) {
            throw new \DomainException('not support total');
        }
        return $this->total;
    }

    public function listRows()
    {
        return $this->listRows;
    }

    public function currentPage()
    {
        return $this->currentPage;
    }

    public function lastPage()
    {
        if ($this->simple) {
            throw new \DomainException('not support last');
        }
        return $this->lastPage;
    }

    /**
     * 数据是否足够分页
     * @return boolean
     */
    public function hasPages()
    {
        return !($this->currentPage == 1 && !$this->hasMore);
    }

    /**
     * 创建一组分页链接
     *
     * @param  int $start
     * @param  int $end
     * @return array
     */
    public function getUrlRange($start, $end)
    {
        $urls = [];

        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    /**
     * 设置URL锚点
     *
     * @param  string|null $fragment
     * @return $this
     */
    public function fragment($fragment)
    {
        $this->options['fragment'] = $fragment;
        return $this;
    }

    /**
     * 添加URL参数
     *
     * @param  array|string $key
     * @param  string|null  $value
     * @return $this
     */
    public function appends($key, $value = null)
    {
        if (!is_array($key)) {
            $queries = [$key => $value];
        } else {
            $queries = $key;
        }

        foreach ($queries as $k => $v) {
            if ($k !== $this->options['var_page']) {
                $this->options['query'][$k] = $v;
            }
        }

        return $this;
    }


    /**
     * 构造锚点字符串
     *
     * @return string
     */
    protected function buildFragment()
    {
        return $this->options['fragment'] ? '#' . $this->options['fragment'] : '';
    }

    /**
     * 渲染分页html
     * @return mixed
     */
    abstract public function render();
}