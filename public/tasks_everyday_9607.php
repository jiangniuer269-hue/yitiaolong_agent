<?php
include_once 'mysql.php';
$mktime = time() - 86400 * 62;
$mktime_seven = time() - 86400 * 5;
$mktime_two = time() - 86400 * 1;
$date_time =  date('Y-m-d H:i:s',$mktime);
//$db_arr = ['shangshui9602', 'shangshui9604', 'shangshui9605'];
//foreach ($db_arr as $db_name) {
$db_name = 'shangshui9607';
echo 'db_name--' . $db_name . PHP_EOL;
$db_host = 'localhost:3603';
$db_user = 'root';
$db_pwd = 'S6HDTh8tSCPFLtaN';
$db = new DBPDO($db_host, $db_user, $db_pwd, $db_name);

echo '删除system_log数据' . PHP_EOL;
$query = "DELETE FROM `system_log` WHERE `mktime`  <='{$mktime}' ";
$db->delete($query);

echo '删除user_score_log数据' . PHP_EOL;
$query = "DELETE FROM `user_score_log` WHERE `time`  <='{$mktime}' and type not in (11,12) ";
$db->delete($query);

echo '删除user_score_log机器人和游客数据' . PHP_EOL;
$query = "DELETE FROM `user_score_log` WHERE user_ai=1";
$db->delete($query);

echo '删除bets_log机器人和游客数据' . PHP_EOL;
$query = "DELETE FROM `bets_log` WHERE (`tourist` =1 OR user_ai=1) AND state=1";
$db->delete($query);


$query = "DELETE FROM `bets_log` WHERE `time` <='{$mktime}'";
$db->delete($query);


echo '备份card_game' . PHP_EOL;
$query = "DELETE FROM `card_game` WHERE `mktime` <='{$mktime_seven}'";
$db->delete($query);

echo '删除card_game_report' . PHP_EOL;
$query = "DELETE FROM `card_game_report` WHERE `mktime` <='{$mktime}'";
$db->delete($query);

echo '删除date_card_game_report' . PHP_EOL;
$query = "DELETE FROM `date_card_game_report` WHERE `mktime` <='{$date_time}'";
$db->delete($query);

echo '删除bets_merge' . PHP_EOL;
$query = "DELETE FROM `bets_merge` WHERE `mktime` <='{$mktime}'";
$db->delete($query);

echo '删除chat_packet数据' . PHP_EOL;
$query = "DELETE FROM `chat_packet` WHERE `createtime` <='{$mktime_two}'";
$db->delete($query);


echo '删除integral_log数据' . PHP_EOL;
$query = "DELETE FROM `integral_log` WHERE user_ai=1";
$db->delete($query);
$query = "DELETE FROM `integral_log` WHERE integral >0 AND `mktime` <='{$mktime_seven}'";
$db->delete($query);

echo '删除agent_score_log数据' . PHP_EOL;
$query = "DELETE FROM `agent_score_log` WHERE `mktime` <='{$mktime}'";
echo $query . PHP_EOL;
$db->delete($query);

echo '删除profit数据' . PHP_EOL;
$query = "DELETE FROM `profit` WHERE `mktime` <='{$date_time}'";
echo $query . PHP_EOL;
$db->delete($query);

echo '删除user_login_info数据' . PHP_EOL;
$query = "DELETE FROM `user_login_info` WHERE `mktime` <='{$mktime}'";
echo $query . PHP_EOL;
$db->delete($query);

echo '删除yard数据' . PHP_EOL;
$query = "DELETE FROM `yard` WHERE `mktime` <='{$mktime}'";
echo $query . PHP_EOL;
$db->delete($query);

echo '删除zy_tongji数据' . PHP_EOL;
$query = "DELETE FROM `zy_tongji` WHERE `mktime` <='{$date_time}'";
echo $query . PHP_EOL;
$db->delete($query);

echo '删除微信用户数据' . PHP_EOL;
$query = "DELETE FROM `user` WHERE `name`  like '%微信用户%'";
echo $query . PHP_EOL;
$db->delete($query);
//}

print_r('执行完毕！');
exit();