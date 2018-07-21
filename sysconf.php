<?php
// +----------------------------------------------------------------------
// | shuguo sysconf
// +----------------------------------------------------------------------
// | Copyright (c) 2018~2050 上海数果科技有限公司 All rights reserved.
// +----------------------------------------------------------------------
// +----------------------------------------------------------------------
// | Website: http://chinashuguo.com
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: opensmarty <opensmarty@163.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 定义系统配置
// +----------------------------------------------------------------------

//APP的常量定义
sgdefine('MODULE_NAME', $sg['moudle']);
sgdefine('CONTROLLER_NAME', $sg['controller']);
sgdefine('ACTION_NAME', $sg['action']);

$widget_appname        = $_REQUEST['widget_appname'];
$sg['_widget_appname'] = isset($widget_appname) && !empty($widget_appname) ? $widget_appname : '';
sgdefine('TRUE_APPNAME', !empty($sg['_widget_appname']) ? $sg['_widget_appname'] : APP_NAME);

//新增一些CODE常量.用于简化判断操作
sgdefine('MODULE_CODE', $sg['moudle'] . '/' . $sg['controller']);
sgdefine('ACTION_CODE', $sg['moudle'] . '/' . $sg['controller'] . '/' . $sg['action']);
sgdefine('APP_RUN_PATH', RUNTIME_PATH . '/~' . TRUE_APPNAME);

/*  应用配置  */
//载入应用配置
sgdefine('APP_PATH', APPS_PATH . '/' . TRUE_APPNAME);
sgdefine('APP_URL', APPS_URL . '/' . TRUE_APPNAME);
sgdefine('APP_COMMON_PATH', APP_PATH . '/common');
sgdefine('APP_COMMAND_PATH', APP_PATH . '/command');
sgdefine('APP_CONFIG_PATH', APP_PATH . '/config');
sgdefine('APP_LANG_PATH', APP_PATH . '/lang');
sgdefine('APP_CONT_PATH', APP_PATH . '/controller');
sgdefine('APP_MODEL_PATH', APP_PATH . '/model');
sgdefine('APP_LOGIC_PATH', APP_PATH . '/logic');
sgdefine('APP_SERVICE_PATH', APP_PATH . '/service');
sgdefine('APP_VALID_PATH', APP_PATH . '/validate');
//定义语言缓存文件路径常量
sgdefine('LANG_PATH', DATA_PATH . '/lang');
sgdefine('LANG_URL', DATA_URL . '/lang');

//默认风格包名称
if (C('theme_name')) {
    sgdefine('THEME_NAME', C('theme_name'));
} else {
    sgdefine('THEME_NAME', 'stv1');
}

//默认静态文件、模版文件目录
sgdefine('THEME_PATH', PUBLIC_PATH . 'theme' . DS);
sgdefine('THEME_URL', PUBLIC_URL . DS . 'theme');
sgdefine('THEME_PUBLIC_PATH', THEME_PATH . 'static' . DS);
sgdefine('THEME_PUBLIC_URL', THEME_URL . DS . 'static');
sgdefine('APP_PUBLIC_PATH', APP_PATH  . 'static' . DS);
sgdefine('APP_TPL_PATH', APP_PATH  . 'view' . DS . 'default' . DS);
sgdefine('APP_TPL_URL', APP_URL . DS . 'view' . DS . 'default');
sgdefine('CANVAS_PATH', SITE_PATH  . 'config' . DS . 'canvas' . DS);

sgdefine('OL_MAP_PATH_URL', ADDON_URL .  DS .'maps' . DS . 'openlayer');

/* 临时兼容代码，新方法开发中 */
//$timer = sprintf('%s%s/app/timer', SG_ROOT, SG_STORAGE);
//if (!file_exists($timer) || (time() - file_get_contents($timer)) > 604800 // 七天更新一次
//) {
//    \shuguo\core\facade\AppInstall::moveAllApplicationResources(); // 移动应用所有的资源
//    \Medz\Component\Filesystem\Filesystem::mkdir(dirname($timer), 0777);
//    file_put_contents($timer, time());
//}
//sgdefine('APP_PUBLIC_URL', sprintf('%s%s/app/%s', SITE_URL, TS_STORAGE, strtolower(APP_NAME)));
//
////根据应用配置重定义以下常量
//if (C('app_tpl_path')) {
//    sgdefine('APP_TPL_PATH', C('app_tpl_path'));
//}
//
////如果是部署模式、则如下定义
//if (C('deploy_static')) {
//    sgdefine('THEME_PUBLIC_URL', PUBLIC_URL . '/' . THEME_NAME);
//    sgdefine('APP_PUBLIC_URL', THEME_PUBLIC_URL . '/' . TRUE_APPNAME);
//}

/**
 * 记录应用路由信息
 */
function routeRecord()
{
//    global $sg;
//
//    $module     = $this->request->module();
//    $controller = $this->request->controller();
//    $action     = $this->request->action();
//    if (!isset($module) && !isset($controller) && !isset($action)) {
//        $sg['module']     = $this->config('default_module');
//        $sg['controller'] = $this->config('default_controller');
//        $sg['action']     = $this->config('default_action');
//    } else {
//        $sg['module']     = isset($module) && !empty($module) ? $module : $this->config('default_module');
//        $sg['controller'] = isset($controller) && !empty($controller) ? $controller : $this->config('default_controller');
//        $sg['action']     = isset($action) && !empty($action) ? $action : $this->config('default_action');
//    }
}