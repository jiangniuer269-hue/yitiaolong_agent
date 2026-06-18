<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/10
 * Time: 21:24
 */
namespace app\index\model;

use think\Model;

class Wxuser extends Model
{
    /**
     * @function 获取无线代
     */
    public static function getWxUserInfo($agents_id, $begin_time = '', $end_time = '', $soso = '')
    {
        $sql = Wxuser::alias('w')->join('user u', 'w.uid=u.uid')->where('w.agent_id', $agents_id)->where('u.deleted', 0)->where('u.ai', 0)
            ->field('u.uid,u.agent_id,u.name,u.head,u.score,u.last_time,u.phone_num,u.agent,u.reg_time,u.status,w.level');
        if (!empty($begin_time)) {
            $sql->where('u.last_time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('u.last_time', '<=', $end_time);
        }
        if (!empty($soso)) {
            $sql->where('u.uid', $soso);
        }
        $user = $sql->select();
       // echo $sql->getLastSql();exit;
        return $user;
    }

    /**
     * @function 获取用户洗码
     */
    public static function getWxUserXm($agents_id)
    {
        Wxuser::alias('w')->join('bets_log b', 'w.uid=b.uid')->where('w.agent_id', $agents_id)->field('b.id,b.uid,b.time,b.type,b.odds,b.win,b.xm,w.level')->select();
    }

    /**
     * @function 获取团队人数
     */
    public static function getWxUserNum($where = [])
    {
        $count = Wxuser::where($where)->count();
        return $count;
    }

    /**
     * @function 获取无限user
     */
    public static function getWxUser($where = [])
    {
        $datas = Wxuser::where($where)->select();
        return $datas;
    }
}