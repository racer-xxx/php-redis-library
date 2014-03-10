<?php
ini_set('default_socket_timeout', -1);  //不超时
define("REDIS_HOST", "127.0.0.1");
define("REDIS_PORT", "6379");
define("REDIS_TIME_OUT", 3);
define("REDIS_ENV", "dev");//pro 环境：开发或生产
define("STORE_METHOD", "");//存储方式json或serialize,为空则直接原值
define("STORE_METHOD_HASH", "json");//hash存储方式json或serialize,为空则直接原值

define("NIL", 0);