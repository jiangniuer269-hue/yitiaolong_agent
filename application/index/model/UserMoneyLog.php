<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\18 0018
 * Time: 14:59
 */

namespace app\index\model;
use think\Model;

class UserMoneyLog extends Model
{
    /**
     * @function 查询用户上下分日志
     */
    public static function selectMoneyLogs()
    {
        $data = UserMoneyLog::alias('m')->join('user','user.uid=m.uid')
            ->field('user.uid,user.head,user.name,user.ai,user.score,m.id,m.money,m.money_change,m.type,m.money_type,m.money_name,m.text,m.yh_name,m.yh_hao')
            ->where('m.state',0)->order('m.id','desc')->select();
        return $data;
    }
}