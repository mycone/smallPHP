<?php
/**
 * 默认控制器
 * @author ChenHao <dzswchenhao@126.com>
 * @copyright 成都一休网络技术有限公司
 */

class IndexController extends Controller {
	
	public function indexAction() {
		$this->assign('out', 'Hello world');
		$this->display();
	}
}