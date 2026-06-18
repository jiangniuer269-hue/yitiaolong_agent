<?php
$xiaoqun_ip = '27.124.46.84';
$daqun_ip = '27.124.46.84';
$domain = $_SERVER['HTTP_HOST'];
$domain_arr = [
    '9309.9527b.cn'   =>['port'=>9309,'agent_id'=>523],

];
$suiji = mt_rand(1,100);
if ($suiji <= 30) {
    $daqun_ip = '27.124.46.84';
}elseif ($suiji >30 && $suiji <= 60){
    $daqun_ip = '27.124.46.84';
}else {
    $daqun_ip = '27.124.46.84';
}
$port = $domain_arr[$domain]['port'];
$agent_id = $domain_arr[$domain]['agent_id'];

header('Location: http://'.$daqun_ip.':'.$port.'/?loginout=1&clean=0&agent_id='.$agent_id.'#/loginbyphone');exit();