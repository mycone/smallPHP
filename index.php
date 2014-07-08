<?php
/**
 * 首页入口文件
 * @author ChenHao <dzswchenhao@126.com>
 * @copyright 成都一休网络技术有限公司
 */

//基本配置
$config = array(
	'APP_PATH'=>'./app',
	'PATH_MOD'=>'PATH_INFO', //路径模式，NORMAL普通模型，PATH_INFO
);
//载入框架核心文件
require_once './app/SmallPHP.php';
//执行
SmallPHP::createApp($config)->run();