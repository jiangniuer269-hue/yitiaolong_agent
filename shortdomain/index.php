<?php
$xiaoqun_ip = '154.23.221.77';
$daqun_ip = '154.23.221.78';
$domain = $_SERVER['HTTP_HOST'];
$domain_arr = [
    'los22.com'   =>['port'=>9304,'agent_id'=>262],
    '9304.gbt111.net' =>['port'=>9304,'agent_id'=>262],
    'a829.com'    =>['port'=>9305,'agent_id'=>5],
    '66606668.com'=>['port'=>9306,'agent_id'=>659],
    '608.ls618.net'   =>['port'=>9306,'agent_id'=>608],
    '2012027.com' =>['port'=>9308,'agent_id'=>525],
    'gbt111.net'  =>['port'=>9309,'agent_id'=>523],
    'foco33.com'  =>['port'=>9309,'agent_id'=>549],
    '9609.yua10.top'  =>['port'=>9309,'agent_id'=>523],
    'xsj188.net'  =>['port'=>9310,'agent_id'=>541],
    '024.xsj188.net'  =>['port'=>9310,'agent_id'=>529],
    '026.xsj188.net'  =>['port'=>9310,'agent_id'=>542],
    '936474.com'  =>['port'=>9311,'agent_id'=>544],
    '028.936474.com'  =>['port'=>9311,'agent_id'=>550],
    '029.936474.com'  =>['port'=>9311,'agent_id'=>558],
    '157199.net'  =>['port'=>9313,'agent_id'=>29],
    '9615.ls618.net' => ['port'=>9313,'agent_id'=>8],
    '027.dz14.com'  =>['port'=>9311,'agent_id'=>544],
    '9613.ls618.net' => ['port'=>9313,'agent_id'=>3],
];
$suiji = mt_rand(1,100);
if ($suiji <= 30) {
    $daqun_ip = '154.23.221.76';
}elseif ($suiji >30 && $suiji <= 60){
    $daqun_ip = '154.23.221.77';
}else {
    $daqun_ip = '154.23.221.78';
}
$port = $domain_arr[$domain]['port'];
$agent_id = $domain_arr[$domain]['agent_id'];

header('Location: http://'.$daqun_ip.':'.$port.'/?loginout=1&clean=0&agent_id='.$agent_id.'#/loginbyphone');exit();