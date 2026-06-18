<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/6/8
 * Time: 11:37 AM
 */

namespace app\index\model;

use think\Model;

class IntegralDate extends Model
{
    /**
     * @function 查询日积分
     *
     * @param array $where
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function selectIntegralData($where = [], $field = '*')
    {
        $data = IntegralDate::where($where)->field($field)->select();
        return $data;
    }


    /**
     * @function 获取剩余积分
     */
    public static function getUserIntegral($where = [])
    {
        $data = IntegralDate::where($where)->field('integral_total')->order('id', 'desc')->limit(1)->find();
        if (!empty($data)) {
            return $data['integral_total'];
        } else {
            return 0;
        }

    }

    /**
     * @function 修改用户积分
     */
    public static function change($uid, $num, $tid)
    {
        $system_info = TeamConfig::where('tid',$tid)->field('integral_tongji_way')->find();
        $integral_tongji_way = intval($system_info['integral_tongji_way']);//积分统计方式：0按自然日1按开工时间
        $start_work_time = 0;
        if ($integral_tongji_way >0) {
            //获取开工时间
            $start_work_time_data = Domain::where('type',16)->field('domain')->find();
            if ($start_work_time_data['domain'] <=0) {
                echo '没有设置开工时间_' . date('Y-m-d H:i:s');exit();
            }
            $start_work_time = intval(date('Ymd',strtotime($start_work_time_data['domain'])));
        }      

        if ($start_work_time>0) {
            $date_time = $start_work_time;
        }else {
            $date_time = intval(date('Ymd'));
        }     
        $data = IntegralDate::where('uid', $uid)->where('tid', $tid)->field('integral_total,id,integral_exchange,date_time,integral')->order('id', 'desc')->limit(1)->find();
        if (!empty($data)) {
            $integral_total = $data['integral_total'] + $num;
            $integral_exchange = $data['integral_exchange'];
            if ($date_time == intval($data['date_time'])) {
                $integral_log_sum = IntegralLog::where('uid', $uid)->where('tid', $tid)->where('date_time', $date_time)->where('integral', '>', 0)->sum('integral');
                if ($num > 0) {
                    $integral = $data['integral'] + $num;
                } else {
                    $integral = $data['integral'];
                }
                if ($integral_log_sum > $integral) {
                    $integral_total += ($integral_log_sum - $integral);
                    $integral = $integral_log_sum;
                } elseif ($integral_log_sum < $integral) {
                    $integral_total -= ($integral - $integral_log_sum);
                    $integral = $integral_log_sum;
                }
                //每日积分增加或减少
                if ($num < 0) {
                    $integral_exchange = $integral_exchange + $num;
                }
                $res = IntegralDate::where('id', $data['id'])->update(
                    [
                        'integral_total' => $integral_total,
                        'integral_exchange' => $integral_exchange,
                        'integral' => $integral
                    ]);
                User::where('uid', $uid)->update(['integral' => $integral_total]);
                if ($res) {
                   return 1;
                }else{
                     return 0;
                }
            } else {
                if ($num > 0) {
                    $integral = $num;
                    $integral_exchange = 0;
                } else {
                    $integral = 0;
                    $integral_exchange = $num;
                }
                $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
                //初始化每日积分数据
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $integral,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 3,
                    'integral_total' => $integral_total,
                    'integral_exchange' => $integral_exchange,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
                User::where('uid', $uid)->update(['integral' => $integral_total]);
                return $insert_id;
            }
        } else {
            //初始化积分表数据
            //uid,date_time,integral,ukey,type,integral_total
            $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
            $insert_id = 0;
            if ($num > 0) {
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $num,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 2,
                    'integral_total' => $num,
                    'integral_exchange' => 0,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
            }
            User::where('uid', $uid)->update(['integral' => $num]);
            return $insert_id;
        }
    }

    /**
     * @function 修改用户积分
     */
    public static function change_lantian($uid, $num, $tid)
    {
        $date_time = date('Ymd');
        $data = IntegralDate::where('uid', $uid)->where('tid', $tid)->field('integral_total,id,integral_exchange,date_time,integral')->order('id', 'desc')->limit(1)->find();
        if (!empty($data)) {
            $integral_total = $data['integral_total'] + $num;
            $integral_exchange = $data['integral_exchange'];
            if ($date_time == intval($data['date_time'])) {
                $integral_log_sum = IntegralLog::where('uid', $uid)->where('tid', $tid)->where('date_time', $date_time)->where('integral', '>', 0)->sum('integral');
                if ($num > 0) {
                    $integral = $data['integral'] + $num;
                } else {
                    $integral = $data['integral'];
                }
                if ($integral_log_sum > $integral) {
                    $integral_total += ($integral_log_sum - $integral);
                    $integral = $integral_log_sum;
                } elseif ($integral_log_sum < $integral) {
                    $integral_total -= ($integral - $integral_log_sum);
                    $integral = $integral_log_sum;
                }
                //每日积分增加或减少
                if ($num < 0) {
                    $integral_exchange = $integral_exchange + $num;
                    if (intval($integral_total) == 0) {
                        $integral_total = 0;
                    }
                }               
                $res = IntegralDate::where('id', $data['id'])->update(
                    [
                        'integral_total' => $integral_total,
                        'integral_exchange' => $integral_exchange,
                        'integral' => $integral
                    ]);
                User::where('uid', $uid)->update(['integral' => $integral_total]);
                
                if ($res) {
                    return 1;
                }else{
                    return 0;
                }
            } else {
                if ($num > 0) {
                    $integral = $num;
                    $integral_exchange = 0;
                } else {
                    $integral = 0;
                    $integral_exchange = $num;
                }
                $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
                //初始化每日积分数据
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $integral,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 3,
                    'integral_total' => $integral_total,
                    'integral_exchange' => $integral_exchange,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
                User::where('uid', $uid)->update(['integral' => $integral_total]);
                
                return $insert_id;
            }
        } else {
            //初始化积分表数据
            //uid,date_time,integral,ukey,type,integral_total
            $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
            $insert_id = 0;
            if ($num > 0) {
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $num,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 2,
                    'integral_total' => $num,
                    'integral_exchange' => 0,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
            }
            User::where('uid', $uid)->update(['integral' => $num]);
            return $insert_id;
        }
    }
    
    
    /**
     * @function 20230302  积分表误删 修复用户用户积分
     */
    public static function change0302($uid, $num, $tid,$date_time)
    {
       // $date_time = date('Ymd');
        $date_time = intval($date_time);
        $data = IntegralDate::where('uid', $uid)->where('tid', $tid)->field('integral_total,id,integral_exchange,date_time,integral')->order('id', 'desc')->limit(1)->find();
        if (!empty($data)) {
            $integral_total = $data['integral_total'] + $num;
            $integral_exchange = $data['integral_exchange'];
            if ($date_time == intval($data['date_time'])) {
                $integral_log_sum = IntegralLog::where('uid', $uid)->where('tid', $tid)->where('date_time', $date_time)->where('integral', '>', 0)->sum('integral');
                if ($num > 0) {
                    $integral = $data['integral'] + $num;
                } else {
                    $integral = $data['integral'];
                }
                if ($integral_log_sum > $integral) {
                    $integral_total += ($integral_log_sum - $integral);
                    $integral = $integral_log_sum;
                } elseif ($integral_log_sum < $integral) {
                    $integral_total -= ($integral - $integral_log_sum);
                    $integral = $integral_log_sum;
                }
                //每日积分增加或减少
                if ($num < 0) {
                    $integral_exchange = $integral_exchange + $num;
                }
                $res = IntegralDate::where('id', $data['id'])->update(
                    [
                        'integral_total' => $integral_total,
                        'integral_exchange' => $integral_exchange,
                        'integral' => $integral
                    ]);
               // User::where('uid', $uid)->update(['integral' => $integral_total]);
                if ($res) {
                    return 1;
                }else{
                    return 0;
                }
            } else {
                if ($num > 0) {
                    $integral = $num;
                    $integral_exchange = 0;
                } else {
                    $integral = 0;
                    $integral_exchange = $num;
                }
                $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
                //初始化每日积分数据
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $integral,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 3,
                    'integral_total' => $integral_total,
                    'integral_exchange' => $integral_exchange,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
               // User::where('uid', $uid)->update(['integral' => $integral_total]);
                return $insert_id;
            }
        } else {
            //初始化积分表数据
            //uid,date_time,integral,ukey,type,integral_total
            $user_info = User::where('uid', $uid)->field('tourist,ai')->find();
            $insert_id = 0;
            if ($num > 0) {
                $insert_id = IntegralDate::insert([
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $num,
                    'ukey' => $uid . '_' . $date_time,
                    'type' => 2,
                    'integral_total' => $num,
                    'integral_exchange' => 0,
                    'tourist' => $user_info['tourist'],
                    'user_ai' => $user_info['ai'],
                    'tid' => $tid
                ], false, true);
            }
           // User::where('uid', $uid)->update(['integral' => $num]);
            return $insert_id;
        }
    }
}