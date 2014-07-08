<?php
/**
 * SmallPHP框架核心文件
 * @author ChenHao <dzswchenhao@126.com>
 */


class SmallPHP {
	/**
	 * 控制器
	 * @var string
	 */
	private $c;
	/**
	 * Action
	 * @var string
	 */
	private $a;
	/**
	 * 单例
	 * @var SmallPHP
	 */
	private static $_instance;

	/**
	 * 构造函数，初始化配置
	 * @param array $config
	 */
	private function __construct($config){
		$this->config($config);
	}
	private function __clone(){}

	/**
	 * 实例化应用（单例）
	 * @param array $config 配置参数
	 * @return SmallPHP
	 */
	public static function createApp($config){
		if(!(self::$_instance instanceof self)){
			self::$_instance = new self($config);
		}
		return self::$_instance;
	}
	
	/**
	 * 返回当前实例
	 */
	public static function app() {
		return self::$_instance;
	}
	
	/**
	 * 配置设置
	 * @param string $key 键
	 * @param string $value
	 * @return NULL|multitype:NULL
	 */
	public function config($key,$value=null){
		static $_config = array();
		$args = func_num_args();
		if($args == 1){
			if(is_string($key)){  //如果传入的key是字符串
				return isset($_config[$key])?$_config[$key]:null;
			}
			if(is_array($key)){
				if(array_keys($key) !== range(0, count($key) - 1)){  //如果传入的key是关联数组
					$_config = array_merge($_config, $key);
				}else{
					$ret = array();
					foreach ($key as $k) {
						$ret[$k] = isset($_config[$k])?$_config[$k]:null;
					}
					return $ret;
				}
			}
		}else{
			if(is_string($key)){
				$_config[$key] = $value;
			}else{
				$this->halt('传入参数不正确');
			}
		}
		return null;
	}
	
	/**
	 * 如果存在则载入
	 * @param string $path 路径
	 */
	public function requireIfExist($path) {
		if(file_exists($path)) {
			require($path);
		}
	}
	
	/**
	 * 错误输出
	 * @param string $str 错误提示内容
	 * @param bool $display 是否开启调试，默认不开启
	 */
	public function halt($str, $display=false){
		header("Content-Type:text/html; charset=utf-8");
		if($display){
			echo "<pre>";
			debug_print_backtrace();
			echo "</pre>";
		}
		echo $str;
		exit;
	}
	
	/**
	 * 运行应用实例
	 * @access public
	 * @return void
	 */
	public function run(){
		if($this->config('USE_SESSION') == true){
			session_start();
		}
		$this->config('APP_FULL_PATH', getcwd().'/'.$this->config('APP_PATH').'/');
		$this->requireIfExist( $this->config('APP_FULL_PATH').'/common.php');
		$pathMod = $this->config('PATH_MOD');
		$pathMod = empty($pathMod)?'NORMAL':$pathMod;
		spl_autoload_register(array('SmallPHP', 'autoload'));
		if(strcmp(strtoupper($pathMod),'NORMAL') === 0 || !isset($_SERVER['PATH_INFO'])){
			$this->c = isset($_GET['c'])?$_GET['c']:'Index';
			$this->a = isset($_GET['a'])?$_GET['a']:'Index';
			if(isset($_GET['c'])) {
				$this->c = $_GET['c'];
				unset($_GET['c']);
			}
			else {
				$this->c = 'Index';
			}
			if(isset($_GET['a'])) {
				$this->a = $_GET['a'];
				unset($_GET['a']);
			}
			else {
				$this->a = 'Index';
			}
			$params = $_GET;
		}else{
			$pathInfo = isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'';
			$pathInfoArr = explode('/',trim($pathInfo,'/'));
			if(isset($pathInfoArr[0]) && $pathInfoArr[0] !== ''){
				$this->c = $pathInfoArr[0];
			}else{
				$this->c = 'Index';
			}
			if(isset($pathInfoArr[1])){
				$this->a = $pathInfoArr[1];
			}else{
				$this->a = 'Index';
			}
			$params = array_slice($pathInfoArr, 2);
		}
		if(!class_exists($this->c.'Controller')){
			$this->halt('控制器'.$this->c.'不存在');
		}
		$controllerClass = $this->c.'Controller';
		$controller = new $controllerClass();
		if(!method_exists($controller, $this->a.'Action')){
			$this->halt('方法'.$this->a.'不存在');
		}
		call_user_func_array(array($controller,$this->a.'Action'),$params);
	}
	
	/**
	 * 自动加载函数
	 * @param string $class 类名
	 */
	public static function autoload($class){
		if(substr($class,-10)=='Controller'){
			SmallPHP::app()->requireIfExist(SmallPHP::app()->config('APP_FULL_PATH').'/controllers/'.$class.'.class.php');
		}elseif(substr($class,-5)=='Model'){
			SmallPHP::app()->requireIfExist(SmallPHP::app()->config('APP_FULL_PATH').'/models/'.$class.'.class.php');
		}else{
			SmallPHP::app()->requireIfExist(SmallPHP::app()->config('APP_FULL_PATH').'/libs/'.$class.'.class.php');
		}
	}
}


/**
 * 控制器类
 */
abstract class Controller {
	/**
	 * 视图实例
	 * @var View
	 */
	private $_view;

	/**
	 * 构造函数，初始化视图实例，调用hook
	 */
	public function __construct(){
		$this->_view = new View();
		$this->_init();
	}

	/**
	 * 前置hook
	 */
	protected function _init(){}
	/**
	 * 渲染模板并输出
	 * @param null|string $tpl 模板文件路径
	 * 参数为相对于app/views/文件的相对路径，不包含后缀名，例如index/index
	 * 如果参数为空，则默认使用$controller/$action.php
	 * 如果参数不包含"/"，则默认使用$controller/$tpl
	 * @return void
	 */
	protected function display($tpl=''){
		if($tpl === ''){
			$trace = debug_backtrace();
			$controller = substr($trace[1]['class'], 0, -10);
			$action = substr($trace[1]['function'], 0 , -6);
			$tpl = $controller . '/' . $action;
		}elseif(strpos($tpl, '/') === false){
			$trace = debug_backtrace();
			$controller = substr($trace[1]['class'], 0, -10);
			$tpl = $controller . '/' . $tpl;
		}
		$this->_view->display($tpl);
	}
	/**
	 * 为视图引擎设置一个模板变量
	 * @param string $name 要在模板中使用的变量名
	 * @param mixed $value 模板中该变量名对应的值
	 * @return void
	 */
	protected function assign($name,$value){
		$this->_view->assign($name,$value);
	}
	/**
	 * 将数据用json格式输出至浏览器，并停止执行代码
	 * @param array $data 要输出的数据
	 */
	protected function ajaxReturn($data){
		echo json_encode($data);
		exit;
	}
	/**
	 * 重定向至指定url
	 * @param string $url 要跳转的url
	 * @param void
	 */
	protected function redirect($url){
		header("Location: $url");
		exit;
	}
}

/**
 * 视图类
 */
class View {
	/**
	 * 视图文件目录
	 * @var string
	 */
	private $_tplDir;
	/**
	 * 视图文件路径
	 * @var string
	 */
	private $_viewPath;
	/**
	 * 视图变量列表
	 * @var array
	 */
	private $_data = array();
	/**
	 * 给tplInclude用的变量列表
	 * @var array
	*/
	private static $tmpData;

	/**
	 * @param string $tplDir
	 */
	public function __construct($tplDir=''){
		if($tplDir == ''){
			$this->_tplDir = SmallPHP::app()->config('APP_PATH').'/views/';
		}else{
			$this->_tplDir = $tplDir;
		}

	}
	/**
	 * 为视图引擎设置一个模板变量
	 * @param string $key 要在模板中使用的变量名
	 * @param mixed $value 模板中该变量名对应的值
	 * @return void
	 */
	public function assign($key, $value) {
		$this->_data[$key] = $value;
	}
	/**
	 * 渲染模板并输出
	 * @param null|string $tplFile 模板文件路径，相对于app/views/文件的相对路径，不包含后缀名，例如index/index
	 * @return void
	 */
	public function display($tplFile) {
		$this->_viewPath = $this->_tplDir . $tplFile . '.php';
		unset($tplFile);
		extract($this->_data);
		include $this->_viewPath;
	}
	/**
	 * 用于在模板文件中包含其他模板
	 * @param string $path 相对于View目录的路径
	 * @param array $data 传递给子模板的变量列表，key为变量名，value为变量值
	 * @return void
	 */
	public static function tplInclude($path, $data=array()){
		self::$tmpData = array(
			'path' => SmallPHP::app()->config('APP_FULL_PATH') . '/views/' . $path . '.php',
			'data' => $data,
		);
		unset($path);
		unset($data);
		extract(self::$tmpData['data']);
		include self::$tmpData['path'];
	}
}
/**
 * 模型
 * @author ChenHao <dzswchenhao@126.com>
 * 所有数据库模型操作需继承此类
 */
class Model {
	//数据表名
	protected $_table;
	//主键
	protected $_pk;
	protected $db;
	public function __construct() {
		$dbConf = SmallPHP::app()->config(array('DB_HOST','DB_PORT','DB_USER','DB_PWD','DB_NAME','DB_CHARSET'));
		$this->db =  DB::getInstance($dbConf);
		$this->init();
	}
	/**
	 * 初始化模型
	 */
	protected function init() {}
	/**
	 * 查询
	 */
	public function find($condition='',$params=array()) {
		
	}
	/**
	 * 保存
	 */
	public function save() {
		
	}
}

/**
 * 数据库操作类
 * 使用方法：
 * DB::getInstance($conf)->query('select * from table');
 * 其中$conf是一个关联数组，需要包含以下key：
 * DB_HOST DB_USER DB_PWD DB_NAME
 * 可以用DB_PORT和DB_CHARSET来指定端口和编码，默认3306和utf8
 */
class DB {
	/**
	 * 数据库链接
	 * @var resource
	 */
	private $_db;
	/**
	 * 保存最后一条sql
	 * @var string
	 */
	private $_lastSql;
	/**
	 * 上次sql语句影响的行数
	 * @var int
	 */
	private $_rows;
	/**
	 * 上次sql执行的错误
	 * @var string
	 */
	private $_error;
	/**
	 * 实例数组
	 * @var array
	 */
	private static $_instance = array();

	/**
	 * 构造函数
	 * @param array $dbConf 配置数组
	*/
	private function __construct($dbConf){
		if(!isset($dbConf['DB_CHARSET'])){
			$dbConf['DB_CHARSET'] = 'utf8';
		}
		$this->_db = mysql_connect($dbConf['DB_HOST'].':'.$dbConf['DB_PORT'],$dbConf['DB_USER'],$dbConf['DB_PWD']);
		if($this->_db === false){
			SmallPHP::app()->halt(mysql_error());
		}
		$selectDb = mysql_select_db($dbConf['DB_NAME'],$this->_db);
		if($selectDb === false){
			SmallPHP::app()->halt(mysql_error());
		}
		mysql_set_charset($dbConf['DB_CHARSET']);
	}
	private function __clone(){}

	/**
	 * 获取DB类
	 * @param array $dbConf 配置数组
	 * @return DB
	 */
	static public function getInstance($dbConf){
		if(!isset($dbConf['DB_PORT'])){
			$dbConf['DB_PORT'] = '3306';
		}
		$key = $dbConf['DB_HOST'].':'.$dbConf['DB_PORT'];
		if(!isset(self::$_instance[$key]) || !(self::$_instance[$key] instanceof self)){
			self::$_instance[$key] = new self($dbConf);
		}
		return self::$_instance[$key];
	}
	/**
	 * 转义字符串
	 * @param string $str 要转义的字符串
	 * @return string 转义后的字符串
	 */
	public function escape($str){
		return mysql_real_escape_string($str, $this->_db);
	}
	/**
	 * 查询，用于select语句
	 * @param string $sql 要查询的sql
	 * @return bool|array 查询成功返回对应数组，失败返回false
	 */
	public function query($sql){
		$this->_rows = 0;
		$this->_error = '';
		$this->_lastSql = $sql;
		$this->logSql();
		$res = mysql_query($sql,$this->_db);
		if($res === false){
			$this->_error = mysql_error($this->_db);
			$this->logError();
			return false;
		}else{
			$this->_rows = mysql_num_rows($res);
			$result = array();
			if($this->_rows >0) {
				while($row = mysql_fetch_array($res, MYSQL_ASSOC)){
					$result[]   =   $row;
				}
				mysql_data_seek($res,0);
			}
			return $result;
		}
	}
	/**
	 * 查询，用于insert/update/delete语句
	 * @param string $sql 要查询的sql
	 * @return bool|int 查询成功返回影响的记录数量，失败返回false
	 */
	public function execute($sql) {
		$this->_rows = 0;
		$this->_error = '';
		$this->_lastSql = $sql;
		$this->logSql();
		$result =   mysql_query($sql, $this->_db) ;
		if ( false === $result) {
			$this->_error = mysql_error($this->_db);
			$this->logError();
			return false;
		} else {
			$this->_rows = mysql_affected_rows($this->_db);
			return $this->_rows;
		}
	}
	/**
	 * 获取上一次查询影响的记录数量
	 * @return int 影响的记录数量
	 */
	public function getRows(){
		return $this->_rows;
	}
	/**
	 * 获取上一次insert后生成的自增id
	 * @return int 自增ID
	 */
	public function getInsertId() {
		return mysql_insert_id($this->_db);
	}
	/**
	 * 获取上一次查询的sql
	 * @return string sql
	 */
	public function getLastSql(){
		return $this->_lastSql;
	}
	/**
	 * 获取上一次查询的错误信息
	 * @return string 错误信息
	 */
	public function getError(){
		return $this->_error;
	}

	/**
	 * 记录sql到文件
	 */
	private function logSql(){
		//Log::sql($this->_lastSql);
	}

	/**
	 * 记录错误日志到文件
	 */
	private function logError(){
		$str = '[SQL ERR]'.$this->_error.' SQL:'.$this->_lastSql;
		//Log::warn($str);
	}
}
