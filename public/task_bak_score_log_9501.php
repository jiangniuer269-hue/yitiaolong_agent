<?php
include_once 'mysql.php';
set_time_limit(0);
//获取日期末尾数
$date_number = substr(date('Ymd'), -1, 1);
//$db_arr = ['shangshui9602', 'shangshui9604', 'shangshui9605'];
//foreach ($db_arr as $db_name) {
$db_name = 'shangshui9501';
$db_host = 'localhost:3603';
$db_user = 'root';
$db_pwd = 'S6HDTh8tSCPFLtaN';
$db = new DBPDO($db_host, $db_user, $db_pwd, $db_name);
$query = "SELECT uid FROM `user_score_log` where  user_ai=0 and tourist=0 and uid like '%{$date_number}' GROUP BY uid ";
$uids = $db->select($query);
foreach ($uids as $item) {
    $uid = $item['uid'];
    echo '会员' . $uid . '执行成功';
    $query = "SELECT `time` FROM `user_score_log` WHERE uid={$uid} ORDER BY id DESC limit 1";
    $res = $db->select($query);
    $time = $res[0]['time'];
    $bak_time = $time - 86400 * 8;
    // $query = "INSERT INTO `user_score_log_bak`(SELECT * FROM `user_score_log` WHERE `time` <='{$bak_time}' AND uid={$uid})";
    // echo $query . PHP_EOL;
    // $db->insert($query);
    $query = "DELETE FROM `user_score_log` WHERE `time` <='{$bak_time}' AND uid={$uid}";
    echo $query . PHP_EOL;
    $db->delete($query);
}
echo $db_name . ' ：user_scor_log备份完成';
//}
echo '执行成功';
