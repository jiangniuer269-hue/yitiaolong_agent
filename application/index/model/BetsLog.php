<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\16 0016
 * Time: 16:28
 */
namespace app\index\model;

use think\Model;

class BetsLog extends Model
{
    /**
     * @function 用户下注记录
     *
     * BetsLog constructor.
     */
    public static function selectBetsLog($where = [])
    {
        $data = BetsLog::where($where)->select();
        return $data;
    }

    /**
     * @function 客户盈亏
     */
    public static function getUserWin($begin, $end)
    {
        $data = BetsLog::where('time', '>=', $begin)->where('time', '<=', $end)->where('state', 1)->where('win_deleted', 0)->sum('win');
        return $data;
    }

    /**
     * @function 庄闲洗码、三宝洗码
     * 
     * @param $begin
     * @param $end
     * @return float|int
     */
    public static function getUserXM($begin,$end,$type=[])
    {
        $data = BetsLog::where('time', '>=', $begin)->where('time', '<=', $end)->where('state', 1)->whereIn('type',$type)->where('xm_deleted', 0)->sum('xm');
        return $data;
    }    
    
    
    

    /**
     * @function 最近战绩
     */
    public static function lastRecord($uid, $begin_time, $end_time)
    {
        $sql = BetsLog::alias('b')->join('card_game c', 'b.card_game_id=c.id')->order('b.id','desc')
            ->where(['b.uid' => $uid, 'b.deleted' => 0, 'b.state' => 1])->where('c.state', 1);
        if (!empty($begin_time)) {
            $sql = $sql->where('b.time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql = $sql->where('b.time', '<=', $end_time);
        }

        $data = $sql->field('b.uid,b.time,b.type,b.odds,b.win,b.xm,c.boots_number,c.ju,c.zhuang,c.zhuang_dui,c.xian_dui,c.room_id')
            ->paginate(10, false, ['query' => ['uid' => $uid, 'begin_time' => $begin_time, 'end_time' => $end_time]]);
        return $data;
    }

    /**
     * @function 最近战绩所有
     */
    public static function lastRecordAll($uid, $begin_time = '', $end_time = '')
    {
        $sql = BetsLog::alias('b')->join('card_game c', 'b.card_game_id=c.id')
            ->where(['b.uid' => $uid, 'b.deleted' => 0, 'b.state' => 1])->where('c.state', 1)->order('b.id','desc');
        if (!empty($begin_time)) {
            $sql = $sql->where('b.time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql = $sql->where('b.time', '<=', $end_time);
        }

        $data = $sql->field('b.id,b.uid,b.time,b.type,b.odds,b.win,b.xm,c.boots_number,c.ju,c.zhuang,c.zhuang_dui,c.xian_dui,c.room_id')->select();
        return $data;
    }
}