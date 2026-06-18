<?php
include_once 'mysql.php';
set_time_limit(0);
//获取日期末尾数
$mktime_seven = (time() - 86400 * 5)*1000;
$db_name = 'leoim16';
$db_host = 'localhost:3603';
$db_user = 'root';
$db_pwd = 'S6HDTh8tSCPFLtaN';
$db = new DBPDO($db_host, $db_user, $db_pwd, $db_name);
$query = "DELETE  FROM `im_message` where  create_at < '%{$mktime_seven}' ";
$uids = $db->select($query);

echo $db_name . ' ：聊天室消息删除成功';

echo '执行成功';
