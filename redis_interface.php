<?php
/**
 * @desc redis 公共类
 * @author wans88
 * @version 2013.12.04
 */
class redis_interface{
	private $_redis;
	private $_log_conn;
	private $_log_error;
	public $con_status;
	
	function __construct(){		
		$this->con_status = true;		
		try {
			require_once __DIR__.'/redisConf.php';
			$this->_redis = new Redis;
			$this->_log_conn = (REDIS_ENV == 'dev') ? (__DIR__."/con.log") : "connection";
			$this->_log_error = (REDIS_ENV == 'dev') ? (__DIR__."/error.log") : "error";
			$this->_redis->connect(REDIS_HOST, REDIS_PORT, REDIS_TIME_OUT);
			$this->_redis->bgSave();
		}
		catch (RedisException $re){
			$this->con_status = false;
// 			throw new RedisException($re->__toString());			
			$this->log($this->_log_conn, "Redis connection error:".$re->getMessage());
// 			$this->__destruct();
			return null;
		}
	}
	
	function __destruct() {
		unset($this->_log_conn,$this->_log_error);
		return null;
	}
	
	/**
	 * @desc 设置$seconds后$key过期
	 * @param string $key
	 * @param int $seconds
	 * @return 1 如果设置了过期时间   0 如果没有设置过期时间，或者不能设置过期时间
	 */
	function expires($key, $seconds){
		return !$this->con_status ? null : $this->_redis->expire($key, $seconds);
	}
	
	/**
	 * @desc 设置在$timestamp时$key过期
	 * @param string $key
	 * @param int $timestamp
	 * @return 1 如果设置了过期时间   0 如果没有设置过期时间，或者不能设置过期时间
	 */
	function expireAt($key, $timestamp){
		!$this->con_status ? null : $this->_redis->expireAt($key, $timestamp);
	}
	
	/**
	 * @desc 是否存在$key
	 * @param unknown $key
	 * @return 存在1不存在0
	 */
	function exists($key){
		return !$this->con_status ? null : $this->_redis->exists($key);
	}
	
	/*************************************************字符串操作START*********************************************************/
	
	/**
	 * @desc 都是覆写旧值，设置单个键值对
	 * @param string $key
	 * @param string $val 注：原生set只能存string
	 * @param int $expire_time
	 * @return boolean
	 */
	function setSingleOverOld($key, $val, $expire_time = null){
		try {		
			if (intval($expire_time) == $expire_time && $expire_time > 0) 
				return !$this->con_status ? null : $this->_redis->setex($key,$expire_time,$this->store_format($val));
			else
				return !$this->con_status ? null : $this->_redis->set($key,$this->store_format($val));
		}
		catch (RedisException $re){
			$this->log($this->_log_error, "Redis set error:".$re->getMessage());
			return false;
		}
	}
	
	/**
	 * @desc 根据键返回单个值
	 * @param string $key
	 * @return string 如果不存在返回null
	 */
	function getSingle($key){
		try {
			$ret = !$this->con_status ? null : $this->_redis->get($key);
			if ($ret === NIL) {
				return null;
			}
			return $this->recover_format($ret);
		}
		catch (RedisException $re){
			$this->log($this->_log_error, "Redis get error:".$re->getMessage());
			return false;
		}
	}
	
	/**
	 * @desc 设置多个键值对，本方法将value全都化为字符串，总是返回true
	 * @param array $key_val_arr
	 * @return boolean
	 */
	function setMultiOverOld($key_val_arr){
		try {
			if (is_array($key_val_arr) && count($key_val_arr) > 0){
				foreach ($key_val_arr as $key => &$value) {
					$value = $this->store_format($value);
				}
			}
			return !$this->con_status ? null : $this->_redis->mset($key_val_arr);
		}
		catch (RedisException $re){
			$this->log($this->_log_error, "Redis setmulti error:".$re->getMessage());
			return false;
		}
	}
	
	/**
	 * @desc 按传入的key顺序依次返回对应的反序列后的值，即使key不存在（该键对应的值返回null）。但键变成了数值
	 * @param array $key_arr
	 * @return array
	 */
	function getMulti($key_arr){
		try {
			$ret = $this->_redis->mget($key_arr);
			if (is_array($ret) && count($ret) > 0) {
				foreach ($ret as $key => &$value) {
					if ($value === NIL)
						$value = null;
					else 
						$value = $this->recover_format($value);
				};
			}
			return $ret;
		}
		catch (RedisException $re){
			$this->log($this->_log_error, "Redis getmulti error:".$re->getMessage());
			return false;
		}
	}
	
	/**
	 * @desc 将给定key的值设为value，并返回key的旧值，如果键不存在就返回null
	 * @param string $key
	 * @param string $value
	 */
	function getsetSingle($key, $value){
		try {
			$ret = $this->_redis->getSet($key, $this->store_format($value));
			return !$this->con_status ? null : ($ret === NIL ? null : $this->recover_format($ret));
		}
		catch (RedisException $re){
			$this->log($this->_log_error, "Redis getset error:".$re->getMessage());
			return false;
		}
	}
	
	/**
	 * @desc 返回key所储存的字符串值的长度,当 key不存在时，返回0
	 * @param string $key
	 * @return int
	 */
	function strlen($key){
		return !$this->con_status ? null : $this->_redis->strlen($key);
	}
	
	/**
	 * @desc $key对应的值增1，如果$key不存在则认为其原值为0，如果原值不能用字符串表示为数字则返回一个错误
	 * @param string $key
	 * @return string 增1后反序列化的value
	 */
	function addOne($key){
		$ret = $this->getSingle($key);
		$ret += 1;
		return !$this->con_status ? null : $this->setSingleOverOld($key, $ret);
	}
	
	/*************************************************字符串操作END*********************************************************/
	
	/*************************************************哈希操作START*********************************************************/
	
	/**
	 * @desc $key不存在就新建，$feild存在则覆盖
	 * @param unknown $key
	 * @param unknown $feild
	 * @param unknown $val
	 * @return 0$feild存在且被覆盖/1$feild不存在且新建设置成功
	 */
	function hset($key, $feild, $val){
		return !$this->con_status ? null : $this->_redis->hSet($key, $feild, $this->store_format_hash($val));
	}
	
	/**
	 * @desc $key不存在就新建，$feild存在则不操作，不存在则设置
	 * @param unknown $key
	 * @param unknown $feild
	 * @param unknown $val
	 * @return 0$feild存在且没有操作执行/1设置成功
	 */
	function hsetnx($key, $feild, $val){
		return !$this->con_status ? null : $this->_redis->hSetNx($key, $feild, $this->store_format_hash($val));
	}
	
// 	/**
// 	 * 
// 	 * @param unknown $key
// 	 * @param array $feild_value_arr
// 	 */
// 	function hmset($key, $feild_value_arr){
// 		foreach ($feild_value_arr as $feild => $value) {
// 			;
// 		}
// 	}
	
	/**
	 * @desc 返回$key中指定$feild值
	 * @param unknown $key
	 * @param unknown $feild
	 */
	function hget($key, $feild){
		$ret = !$this->con_status ? null : $this->_redis->hGet($key, $feild);
		return ($ret === NIL) ? null : $this->recover_format_hash($ret);
	}
	
	/**
	 * @desc 返回$key所有feild和value
	 * @param unknown $key
	 */
	function hgetall($key){
		if(!$this->con_status)
			return array();
		$arr = $this->_redis->hGetAll($key);
		if (is_array($arr) && count($arr) > 0) {
			foreach ($arr as $key => &$value) {
				$value = $this->recover_format_hash($value);
			};
		}
		return $arr;
	}
	
	/**
	 * @desc 删除$key中一个或多个$feild（多个在此暂不实现）
	 * @param unknown $key
	 * @param unknown $feild
	 * @return int 被成功删除的数量（不含被忽略的feild）
	 */
	function hdel($key, $feild){
		return !$this->con_status ? null : $this->_redis->hDel($key, $feild);
	}
	
	/**
	 * @desc $key中$feild数量
	 * @param unknown $key
	 * @desc int 当$key不存在，返回0
	 */
	function hlen($key){
		return !$this->con_status ? null : $this->_redis->hLen($key);
	}
	
	/**
	 * @desc $key中$feild是否存在
	 * @param unknown $key
	 * @param unknown $feild
	 * @return 存在1，$key或$feild不存在0
	 */
	function hexists($key, $feild){
		return !$this->con_status ? null : $this->_redis->hExists($key, $feild);
	}
	
	/**
	 * @desc $key中$feild的value加$increment（可为负）
	 * @param unknown $key
	 * @param unknown $feild
	 * @param unknown $increment
	 * @return 返回增后value。$key不存在就新建，$feild不存在就建原值为0再增，对字符串操作报错。
	 */
	function hincrby($key, $feild, $increment){
		return !$this->con_status ? null : $this->_redis->hIncrBy($key, $feild, $increment);
	}
	
	/**
	 * @desc $key中所有$feild
	 * @param unknown $key
	 * @return $key中所有$feild表，$key不存在则空表
	 */
	function hkeys($key){
		return !$this->con_status ? null : $this->_redis->hKeys($key);
	}
	
	/**
	 * @desc $key中所有值
	 * @param unknown $key
	 * @return $key中所有值表，$key不存在则空表
	 */
	function hvals($key){
		return !$this->con_status ? null : $this->_redis->hVals($key);
	}
	
	/*************************************************哈希操作END*********************************************************/
	
	/**
	 * @desc 记录日志到数据库或文件
	 * @param string $type
	 * @param string $content
	 */
	function log($type, $content){
		if (REDIS_ENV == 'dev') {
			file_put_contents($type, $content."\r\n", FILE_APPEND);
		}
		else{
			//记进数据库
		}
	}	
	
	/**
	 * @desc 按某种格式格式化值
	 * @param mixed $value
	 * @return string
	 */
	function store_format($value){
		if (STORE_METHOD == 'json') {
			return json_encode($value);
		}
		elseif (STORE_METHOD == 'serialize'){
			return serialize($value);
		}
		return strval($value);
	}
	
	/**
	 * @desc 按某种格式逆格式化值
	 * @param string $value
	 * @return mixed
	 */
	function recover_format($value){
		if (STORE_METHOD == 'json') {
			return json_decode($value, true);
		}
		elseif (STORE_METHOD == 'serialize'){
			return unserialize($value);
		}
		return $value;
	}
	
	/**
	 * @desc 按某种格式格式化值
	 * @param mixed $value
	 * @return string
	 */
	function store_format_hash($value){
		if (STORE_METHOD_HASH == 'json') {
			return json_encode($value);
		}
		elseif (STORE_METHOD_HASH == 'serialize'){
			return serialize($value);
		}
		return strval($value);
	}
	
	/**
	 * @desc 按某种格式逆格式化值
	 * @param string $value
	 * @return mixed
	 */
	function recover_format_hash($value){
		if (STORE_METHOD_HASH == 'json') {
			return json_decode($value, true);
		}
		elseif (STORE_METHOD_HASH == 'serialize'){
			return unserialize($value);
		}
		return $value;
	}
}