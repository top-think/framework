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

namespace think;

use think\App;
use think\Cache;
use think\Config;
use think\Request;
use think\Route;

class Url
{
    // 生成URL地址的root
    protected static $root;

    /**
     * URL生成 支持路由反射
     * @param string            $url URL表达式，
     * 格式：'[模块/控制器/操作]?参数1=值1&参数2=值2...@域名'
     * @控制器/操作?参数1=值1&参数2=值2...
     * \\命名空间类\\方法?参数1=值1&参数2=值2...
     * @param string|array      $vars 传入的参数，支持数组和字符串
     * @param string|bool       $suffix 伪静态后缀，默认为true表示获取配置值
     * @param boolean|string    $domain 是否显示域名 或者直接传入域名
     * @return string
     */
    public static function build($url = '', $vars = '', $suffix = true, $domain = false)
    {
        if (false === $domain && Config::get('url_domain_deploy')) {
            $domain = true;
        }
        // 解析URL
        $info = parse_url($url);
        $url  = !empty($info['path']) ? $info['path'] : '';
        if (isset($info['fragment'])) {
            // 解析锚点
            $anchor = $info['fragment'];
            if (false !== strpos($anchor, '?')) {
                // 解析参数
                list($anchor, $info['query']) = explode('?', $anchor, 2);
            }
            if (false !== strpos($anchor, '@')) {
                // 解析域名
                list($anchor, $domain) = explode('@', $anchor, 2);
            }
        } elseif (strpos($url, '@')) {
            // 解析域名
            list($url, $domain) = explode('@', $url, 2);
        }

        // 解析参数
        if (is_string($vars)) {
            // aaa=1&bbb=2 转换成数组
            parse_str($vars, $vars);
        }

        if (isset($info['query'])) {
            // 解析地址里面参数 合并到vars
            parse_str($info['query'], $params);
            $vars = array_merge($params, $vars);
        }

        // 获取路由别名
        $alias = self::getRouteAlias();
        // 检测路由
        if (0 !== strpos($url, '/') && isset($alias[$url]) && $match = self::getRouteUrl($alias[$url], $vars)) {
            // 处理路由规则中的特殊字符
            $url = str_replace('[--think--]', '', $match);
        } else {
            // 路由不存在 直接解析
            $url = self::parseUrl($url);
        }

        // 检测URL绑定
        $type = Route::getBind('type');
        if ($type) {
            $bind = Route::getBind($type);
            if (0 === strpos($url, $bind)) {
                $url = substr($url, strlen($bind) + 1);
            }
        }
        // 还原URL分隔符
        $depr = Config::get('pathinfo_depr');
        $url  = str_replace('/', $depr, $url);

        // URL后缀
        $suffix = in_array($url, ['/', '']) ? '' : self::parseSuffix($suffix);
        // 锚点
        $anchor = !empty($anchor) ? '#' . $anchor : '';
        // 参数组装
        if (!empty($vars)) {
            // 添加参数
            if (Config::get('url_common_param')) {
                $vars = urldecode(http_build_query($vars));
                $url .= $suffix . '?' . $vars . $anchor;
            } else {
                foreach ($vars as $var => $val) {
                    if ('' !== trim($val)) {
                        $url .= $depr . $var . $depr . urlencode($val);
                    }
                }
                $url .= $suffix . $anchor;
            }
        } else {
            $url .= $suffix . $anchor;
        }
        // 检测域名
        $domain = self::parseDomain($url, $domain);
        // URL组装
        $url = $domain . (self::$root ?: Request::instance()->root()) . '/' . ltrim($url, '/');
        return $url;
    }

    // 直接解析URL地址
    protected static function parseUrl($url)
    {
        $request = Request::instance();
        if (0 === strpos($url, '/')) {
            // 直接作为路由地址解析
            $url = substr($url, 1);
        } elseif (false !== strpos($url, '\\')) {
            // 解析到类
            $url = ltrim(str_replace('\\', '/', $url), '/');
        } elseif (0 === strpos($url, '@')) {
            // 解析到控制器
            $url = substr($url, 1);
        } else {
            // 解析到 模块/控制器/操作
            $module     = $request->module();
            $module     = $module ? $module . '/' : '';
            $controller = $request->controller();
            if ('' == $url) {
                // 空字符串输出当前的 模块/控制器/操作
                $url = $module . $controller . '/' . $request->action();
            } else {
                $path       = explode('/', $url);
                $action     = Config::get('url_convert') ? strtolower(array_pop($path)) : array_pop($path);
                $controller = empty($path) ? $controller : (Config::get('url_convert') ? Loader::parseName(array_pop($path)) : array_pop($path));
                $module     = empty($path) ? $module : array_pop($path) . '/';
                $url        = $module . $controller . '/' . $action;
            }
        }
        return $url;
    }

    // 检测域名
    protected static function parseDomain(&$url, $domain)
    {
        if (!$domain) {
            return '';
        }
        $request = Request::instance();
        if (true === $domain) {
            // 自动判断域名
            $domain = $request->host();
            if (Config::get('url_domain_deploy')) {
                // 根域名
                $urlDomainRoot = Config::get('url_domain_root');
                $domains       = Route::rules('domain');
                $route_domain  = array_keys($domains);
                foreach ($route_domain as $domain_prefix) {
                    if (0 === strpos($domain_prefix, '*.') && strpos($domain, ltrim($domain_prefix, '*.')) !== false) {
                        foreach ($domains as $key => $rule) {
                            $rule = is_array($rule) ? $rule[0] : $rule;
                            if (false === strpos($key, '*') && 0 === strpos($url, $rule)) {
                                $url    = ltrim($url, $rule);
                                $domain = $key;
                                // 生成对应子域名
                                if (!empty($urlDomainRoot)) {
                                    $domain .= $urlDomainRoot;
                                }
                                break;
                            } else if (false !== strpos($key, '*')) {
                                if (!empty($urlDomainRoot)) {
                                    $domain .= $urlDomainRoot;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            $domain .= strpos($domain, '.') ? '' : strstr($request->host(), '.');
        }
        $domain = ($request->isSsl() ? 'https://' : 'http://') . $domain;
        return $domain;
    }

    // 检测路由规则中的变量是否有传入
    protected static function pattern($pattern, $vars)
    {
        foreach ($pattern as $key => $type) {
            if (1 == $type && !isset($vars[$key])) {
                // 变量未设置
                return false;
            }
        }
        return true;
    }

    // 解析URL后缀
    protected static function parseSuffix($suffix)
    {
        if ($suffix) {
            $suffix = true === $suffix ? Config::get('url_html_suffix') : $suffix;
            if ($pos = strpos($suffix, '|')) {
                $suffix = substr($suffix, 0, $pos);
            }
        }
        return (empty($suffix) || 0 === strpos($suffix, '.')) ? $suffix : '.' . $suffix;
    }

    // 匹配路由地址
    public static function getRouteUrl($alias, &$vars = [])
    {
        foreach ($alias as $key => $val) {
            list($url, $pattern, $param) = $val;
            // 解析安全替换
            if (strpos($url, '$')) {
                $url = str_replace('$', '[--think--]', $url);
            }
            // 检查变量匹配
            $array = $vars;
            $match = false;
            if ($pattern && self::pattern($pattern, $vars)) {
                foreach ($pattern as $key => $val) {
                    if (isset($vars[$key])) {
                        $url = str_replace(['[:' . $key . ']', '<' . $key . '?>', ':' . $key . '', '<' . $key . '>'], $vars[$key], $url);
                        unset($array[$key]);
                    } else {
                        $url = str_replace(['[:' . $key . ']', '<' . $key . '?>'], '', $url);
                    }
                }
                $match = true;
            } elseif (empty($pattern) && array_intersect_assoc($param, $array) == $param) {
                $match = true;
            }
            if ($match && !empty($param) && array_intersect_assoc($param, $array) != $param) {
                $match = false;
            }
            if ($match) {
                // 存在变量定义
                $vars = array_diff_key($array, $param);
                return $url;
            }
        }
        return false;
    }

    // 生成路由映射并缓存
    private static function getRouteAlias()
    {
        if ($item = Cache::get('think_route_map')) {
            return $item;
        }
        // 获取路由定义
        $array = Route::rules();
        foreach ($array as $type => $rules) {
            foreach ($rules as $rule => $val) {
                if (true === $val || empty($val['rule'])) {
                    continue;
                }
                $route = $val['route'];
                $vars  = $val['var'];
                if (is_array($val['rule'])) {
                    foreach ($val['rule'] as $val) {
                        $key   = $val['rule'];
                        $route = $val['route'];
                        $var   = $val['var'];
                        $param = [];
                        if (is_array($route)) {
                            $route = implode('\\', $route);
                        } elseif ($route instanceof \Closure) {
                            continue;
                        } elseif (strpos($route, '?')) {
                            list($route, $str) = explode('?', $route, 2);
                            parse_str($str, $param);
                        }
                        $var            = array_merge($vars, $var);
                        $item[$route][] = [$rule . '/' . $key, $var, $param];
                    }
                } else {
                    $param = [];
                    if (is_array($route)) {
                        $route = implode('\\', $route);
                    } elseif ($route instanceof \Closure) {
                        continue;
                    } elseif (strpos($route, '?')) {
                        list($route, $str) = explode('?', $route, 2);
                        parse_str($str, $param);
                    }
                    $item[$route][] = [$rule, $vars, $param];
                }
            }
        }

        // 检测路由别名
        $alias = Route::rules('alias');
        foreach ($alias as $rule => $route) {
            $route          = is_array($route) ? $route[0] : $route;
            $item[$route][] = [$rule, [], []];
        }
        !App::$debug && Cache::set('think_route_map', $item);
        return $item;
    }

    // 清空路由别名缓存
    public static function clearAliasCache()
    {
        Cache::rm('think_route_map');
    }

    // 指定当前生成URL地址的root
    public static function root($root)
    {
        self::$root = $root;
        Request::instance()->root($root);
    }
}
