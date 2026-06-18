<?php
$xiaoqun_ip = '143.92.61.153';
$daqun_ip = '143.92.61.167';
$domain = $_SERVER['HTTP_HOST'];
$domain_arr = [
    'qp618.net'    =>['port'=>9305,'agent_id'=>5],
    '161ka.com'    =>['port'=>9305,'agent_id'=>5],
    '88.qp618.net'    =>['port'=>9309,'agent_id'=>549],
    '027.161ka.com'  =>['port'=>9311,'agent_id'=>544],
    '9616.161ka.com'  =>['port'=>9313,'agent_id'=>29],
    'psm999.com'  =>['port'=>9304,'agent_id'=>262],
    'jmjx666.com' =>['port'=>9304,'agent_id'=>262],
    '055.psm999.com'  =>['port'=>9309,'agent_id'=>549],
    '9304.jmjx666.com' =>['port'=>9304,'agent_id'=>262],
    '9308.qp618.net' =>['port'=>9308,'agent_id'=>525],
    '9615.jmjx666.com' =>['port'=>9313,'agent_id'=>8],
    '027.5g328.com'  =>['port'=>9311,'agent_id'=>544],
    '9613.jmjx666.com' =>['port'=>9313,'agent_id'=>3],
    '9609.yuan2.top'    =>['port'=>9309,'agent_id'=>523],
];
$port = $domain_arr[$domain]['port'];
$agent_id = $domain_arr[$domain]['agent_id'];
$suiji = mt_rand(1,100);
if ($suiji <= 30) {
    $daqun_ip = '143.92.61.167';
}elseif ($suiji >30 && $suiji <= 60){
    $daqun_ip = '143.92.61.153';
}else {
    $daqun_ip = '143.92.61.151';
}
header('Location: http://'.$daqun_ip.':'.$port.'/?loginout=1&clean=0&agent_id='.$agent_id.'#/loginbyphone');exit();