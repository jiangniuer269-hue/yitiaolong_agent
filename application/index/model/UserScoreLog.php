<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\21 0021
 * Time: 17:39
 */
namespace app\index\model;

use think\Db;
use think\Model;

class UserScoreLog extends Model
{
    /**
     * @function 积分变更记录
     *
     * @param $uid
     * @param $num
     * @return bool|mixed
     */
    public static function change($uid, $num, $orderid)
    {
        if ($uid == 0 || $num == 0) {
            return false;
        }
        Db::startTrans();
        try {
            //获取用户余额
            $user = Db::query("SELECT uid,score FROM user WHERE uid={$uid} ");
            if (!$user) {
                return false;
            }
            $current = $user[0]['score'];
            //下分，用户余额不足
            if ($num < 0 && $current < abs($num)) {
                return false;
            }

            if ($num > 0) {
                $type = 20;
            } else {
                $type = 21;
            }
            //变化后的值
            $after = $current + $num;
            $log = new UserScoreLog([
                'uid' => $uid,
                'score' => $current,
                'score_change' => $num,
                'score_after' => $after,
                'type' => $type,
                'time' => time(),
                'orderid' => $orderid,
                'ukey' => $type . '_' . $orderid
            ]);
            $log->save();
            $logId = $log->id;
            if ($logId > 0) {
                User::where('uid', $uid)->update(['score' => $after]);
                Db::commit();
                return $logId;
            } else {
                Db::rollback();
                return false;
            }
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
}