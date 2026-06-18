<?php
/**
 * PHP定时任务
 * @author wuquanyao <git@yeahphp.com>
 * @link http://www.yeahphp.com
 */

/**
 * nohup php  /home/www/wwwroot/agent_shangshui/php-task-qilin-robot/qilinrobot5run.php start --log=false > /dev/null &
 * 任务列表
 * 格式:[执行间隔秒数, 要执行的命令]
 */
return
[

    [3, "curl http://154.23.221.76:9772/v1/robotBets/todorobot"],
];
