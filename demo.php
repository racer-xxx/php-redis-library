<?php
require_once 'redis_interface.php';
$redis = new redis_interface();
if (!$redis->con_status){
	echo 'redis 无法连接!';
	exit;
}
//然后就可以调用其中的方法了
$redis->addone('test_key');