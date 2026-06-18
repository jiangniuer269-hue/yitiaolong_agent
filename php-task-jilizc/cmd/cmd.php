<?php
/**
 * PHP定时任务
 * @author wuquanyao <git@yeahphp.com>
 * @link http://www.yeahphp.com
 */

/**
 * 任务列表
 * 格式:[执行间隔秒数, 要执行的命令]
 */
return
[
    //每隔2秒
    [2, "curl http://154.23.221.76:9764/v1/data/updateReport"],
];
