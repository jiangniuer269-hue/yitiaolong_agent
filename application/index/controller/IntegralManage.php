<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/6/8
 * Time: 11:33 AM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\AgentsIntegralLog;
use app\index\model\IntegralDate;
use app\index\model\IntegralLog;
use app\index\model\teamroom;
use app\index\model\Wxagents;
use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\model\User;
use app\index\model\Agents;
use app\index\model\AgentScoreLog;

class IntegralManage
{
    public function __construct()
    {
        common::checkLogin();
    }


    /**
     * @function 日积分流水
     */
    public function integralDate()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $userId = $request->post('uid');
        $data_type = intval($request->post('dataType'));
        $agents_account = $request->post('agents_account');
        $doSearchDetail = intval($request->post('doSearchDetail'));
        $integral_all = 0;//累计产生积分
        $integral_exchange = 0; //累计已提积分
        $user_integral = 0;//累计剩余积分
        $agents_id = common::changeAgentId($agents_id);
        $sql = IntegralDate::alias('inte')->field('inte.id,inte.uid,inte.date_time,inte.integral,inte.integral_total,inte.integral_exchange,u.name,u.agents_id,u.agents_account,u.agents_name,u.integral as user_integral,u.xm_rate')
        ->join('user u', 'inte.uid=u.uid')->join('wxagents wx', 'u.agents_id=wx.agents_id')
        ->where('wx.boss_id', $agents_id)->order('inte.id', 'desc')->where('inte.tid', $tid);
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $sql->where('u.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = date('Ymd', strtotime($begin_time));
            $sql->where('inte.date_time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = date('Ymd', strtotime($end_time));
            $sql->where('inte.date_time', '<=', $end_time_sql);
        }
        if (!empty($userId)) {
            $sql->where('inte.uid', $userId);
        }
        if ($agents_id == 1) {
            if ($data_type == 1) {
                $sql->where('u.tourist', 0)->where('u.ai', 0);
            } elseif ($data_type == 2) {
                $sql->where('u.tourist', 1)->whereOr('u.ai', 1);
            }
        } else {
            $sql->where('u.tourist', 0)->where('u.ai', 0);
        }
        $datas = $sql->select();
        $return_data = [];
        $return_data_uid = [];
        foreach ($datas as $item) {
            $uid = $item['uid'];
            if (!empty($begin_time) && !empty($end_time) && $doSearchDetail !=1) {
                if (empty($return_data_uid[$uid])) {
                    $return_data_uid[$uid] = [
                        'uid' => $uid,
                        'agents_account' => $item['agents_account'],
                        'agents_name' => $item['agents_name'],
                        'agents_id' => $item['agents_id'],
                        'name' => $item['name'],
                        'date' => substr($begin_time, 0, 10) . '-' . substr($end_time, 0, 10),
                        'date_time' => $item['date_time'],
                        'integral' => $item['integral'],
                        'integral_exchange' => floor($item['integral_exchange']),
                        'integral_total' => floor($item['integral_total']),
                        'xm_rate' => $item['xm_rate'], 
                        'begin_time'=>$begin_time,
                        'end_time'=>$end_time
                    ];
                } else {
                    $return_data_uid[$uid]['integral'] = common::math_add($item['integral'], $return_data_uid[$uid]['integral'], 2);
                    $return_data_uid[$uid]['integral_exchange'] = common::math_add($item['integral_exchange'], $return_data_uid[$uid]['integral_exchange'], 2);
                }
            } else {
                $date_time = $item['date_time'];
                $return_data[] = [
                    'uid' => $uid,
                    'agents_account' => $item['agents_account'],
                    'agents_id' => $item['agents_id'],
                    'name' => $item['name'],
                    'date' => substr($date_time, 0, 4) . '-' . substr($date_time, 4, 2) . '-' . substr($date_time, 6, 2),
                    'date_time' => $date_time,
                    'integral' => floor($item['integral']),
                    'integral_exchange' => floor($item['integral_exchange']),
                    'integral_total' => floor($item['integral_total']),
                    'xm_rate' => $item['xm_rate'],
                    'begin_time'=>$begin_time,
                    'end_time'=>$end_time
                ];
            }
        }
        $uid_array = [];
        foreach ($datas as $value) {
            $integral_all = $value['integral'] + $integral_all;
            $integral_exchange += $value['integral_exchange'];
            if (!in_array($value['uid'], $uid_array)) {
                $uid_array[] = $value['uid'];
            }
        }

        foreach ($return_data_uid as $value1) {
            $value1['integral'] = floor($value1['integral']);
            $return_data[] = $value1;
        }

        $user_integral = User::whereIn('uid', $uid_array)->sum('integral');
        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => $return_data,
                'integral_all' => floor($integral_all),
                'integral_exchange' => floor($integral_exchange),
                'user_integral' => floor($user_integral)
            ]];
    }


    /**
     * @function 导出报表
     */
    public function integralExport()
    {
        ini_set('date.timezone','Asia/Shanghai');
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $userId = $request->post('uid');
        $data_type = intval($request->post('dataType'));
        $agents_account = $request->post('agents_account');
        $integral_all = 0;//累计产生积分
        $integral_exchange = 0; //累计已提积分
        $user_integral = 0;//累计剩余积分
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $sql = IntegralDate::alias('inte')->field('inte.id,inte.uid,inte.date_time,inte.integral,inte.integral_total,inte.integral_exchange,u.name,u.agents_id,u.agents_account,u.integral as user_integral,u.xm_rate')
            ->join('user u', 'inte.uid=u.uid')->order('inte.id', 'desc');
        if ($agents_id != 1) {
            $sql->where('u.agents_id', 'IN', function ($query) use ($agents_id) {
                $query->table('wxagents')->where('boss_id', $agents_id)->field('agents_id');
            });
        }
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $sql->where('u.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        if (!empty($begin_time)) {
            $begin_time_sql = date('Ymd', strtotime(substr($request->post('begin_time'), 0, 34)));
            $sql->where('inte.date_time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = date('Ymd', strtotime(substr($request->post('end_time'), 0, 34)));
            $sql->where('inte.date_time', '<=', $end_time_sql);
        }
        if (!empty($userId)) {
            $sql->where('inte.uid', $userId);
        }
        if ($agents_id == 1) {
            if ($data_type == 1) {
                $sql->where('u.tourist', 0)->where('u.ai', 0);
            } elseif ($data_type == 2) {
                $sql->where('u.tourist', 1)->whereOr('u.ai', 1);
            }
        } else {
            $sql->where('u.tourist', 0)->where('u.ai', 0);
        }
        $datas = $sql->select();
        $return_data = [];
        $return_data_uid = [];
        foreach ($datas as $item) {
            $uid = $item['uid'];
            if (!empty($begin_time) && !empty($end_time)) {
                if (empty($return_data_uid[$uid])) {
                    $return_data_uid[$uid] = [
                        'uid' => $uid,
                        'agents_account' => $item['agents_account'],
                        'agents_id' => $item['agents_id'],
                        'name' => $item['name'],
                        'date' => date('Ymd', strtotime(substr($request->post('begin_time'), 0, 34))) . '-' . date('Ymd', strtotime(substr($request->post('end_time'),0,34))),
                        'date_time' => $item['date_time'],
                        'integral' => $item['integral'],
                        'integral_exchange' => floor($item['integral_exchange']),
                        'integral_total' => floor($item['integral_total']),
                        'xm_rate' => $item['xm_rate']
                    ];
                } else {
                    $return_data_uid[$uid]['integral'] = common::math_add($item['integral'], $return_data_uid[$uid]['integral'], 2);
                    $return_data_uid[$uid]['integral_exchange'] = common::math_add($item['integral_exchange'], $return_data_uid[$uid]['integral_exchange'], 2);
                }
            } else {
                $date_time = $item['date_time'];
                $return_data[] = [
                    'uid' => $uid,
                    'agents_account' => $item['agents_account'],
                    'agents_id' => $item['agents_id'],
                    'name' => $item['name'],
                    'date' => substr($date_time, 0, 4) . '-' . substr($date_time, 4, 2) . '-' . substr($date_time, 6, 2),
                    'date_time' => $date_time,
                    'integral' => floor($item['integral']),
                    'integral_exchange' => floor($item['integral_exchange']),
                    'integral_total' => floor($item['integral_total']),
                    'xm_rate' => $item['xm_rate']
                ];
            }
        }
        $uid_array = [];
        foreach ($datas as $value) {
            $integral_all = $value['integral'] + $integral_all;
            $integral_exchange += $value['integral_exchange'];
            if (!in_array($value['uid'], $uid_array)) {
                $uid_array[] = $value['uid'];
            }
        }

        foreach ($return_data_uid as $value1) {
            $value1['integral'] = floor($value1['integral']);
            $return_data[] = $value1;
        }

        $user_integral = User::whereIn('uid', $uid_array)->sum('integral');

        $excelData = [["累计产生积分",floor($integral_all), "累计已提积分",floor($integral_exchange),'总剩余积分',floor($user_integral)]];
        array_push($excelData, ["会员ID", "会员名称", "每日积分", "已提积分", "剩余积分","积分比例", "时间"]);
        foreach ($return_data as $key => $value) {
            $insert = [];
            array_push($insert, $value['uid']);
            array_push($insert, $value['name']);
            array_push($insert, $value['integral']);
            array_push($insert, $value['integral_exchange']);
            array_push($insert, $value['integral_total']);
            array_push($insert, $value['xm_rate']);
            array_push($insert, $value['date']);
            array_push($excelData, $insert);
        }
        //写excel
        if(!empty($begin_time)){
            $title = "会员积分" . date("Y-m-d", strtotime(substr($request->post('begin_time'), 0, 34))) . '-' . date("Y-m-d", strtotime(substr($request->post('end_time'),0,34)));
        }else{
            $title = "会员积分-全部";
        }

        common::exportExcel($title, "会员积分", "xls", $excelData);

        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => $return_data,
                'integral_all' => floor($integral_all),
                'integral_exchange' => floor($integral_exchange),
                'user_integral' => floor($user_integral)
            ]];
    }

    /**
     * @function 积分详情
     */
    public function integralLog()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_time = strtotime($request->post('begin_time'));
        $end_time = strtotime($request->post('end_time'));
        $uid = $request->post('uid');
        $date_time = $request->post('date_time');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        $sql = IntegralLog::alias('inte')->join('user u', 'inte.uid=u.uid');
        $sql->where('u.tourist', 0)->where('u.ai', 0)->where('inte.uid', $uid);
        $sql->field('inte.id,inte.uid,inte.score,inte.mktime,inte.integral,inte.card_game_id,inte.type,u.name');
        $sql->order('inte.id', 'desc');
        $sql->where('inte.tid', $tid);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('inte.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('inte.mktime', '<=', $end_time_sql);
        }
        if (!empty($date_time)) {
            $sql->where('inte.date_time', $date_time);
        }
        $datas = $sql->limit($start, $pageSize)->select();
        $datasAll = $sql->count();
        foreach ($datas as &$item) {
            $item['mktime'] = date('Y-m-d H:i:s', $item['mktime']);
        }
        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => $datas,
                'total' => $datasAll,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
            ]];
    }


    /**
     * @function 手动上下积分
     */
    public function integral_hand()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
//        if (common::checkEmp($agents_id) != 3) {//财务账号
//            return ['code' => 500, 'msg' => '没权限操作'];
//        }
        $agents = Agents::where('agents_id', $agents_id)->where('tid', $tid)->field('agent_type,account,name')->find();
        if ($agents['agent_type'] != 2 && $agents['agent_type'] != 3 ){
            return ['code' => 500, 'msg' => '没权限操作'];
        }
        $integral_exchange = abs(intval($request->post('integral_exchange')));
        $countType = abs(intval($request->post('countType')));
        $type = abs(intval($request->post('type')));
        $uid = intval($request->post('uid'));
        $user = User::where('uid', $uid)->where('tid', $tid)->field('xm_rate,tourist,ai,username,uid')->find();
        if (empty($user)) {
            return ['code' => 500, 'msg' => '会员不存在'];
        }
        $inte_rate = $user['xm_rate'];
        $inte_type = '正常结算';
        if ($countType == 1 && $inte_rate >= 1) { //提前结算，积分减的一
            //获取系统流水比例
           // $system = teamroom::where('id', 1)->field('integral_rate')->find();
           // $integral_rate = $system['integral_rate'] / 1000;
            $inte_rate = $inte_rate - 1;
            $inte_type = '提前结算';
        }
        //获取剩余积分
        $integral = IntegralDate::getUserIntegral(['uid' => $uid]);
        $insertType = 101;
        if ($integral_exchange != 0) {
//            if ($type == 1) {//上积分
//                $insertType = 101;
//            } else
            if ($type == 2) {//下积分
                $insertType = 102;
                if ($countType == 1) {
                    $insertType = 104;
                }
                $integral_exchange = -$integral_exchange;
            } else {
                return ['code' => 500, 'msg' => '非法操作'];
            }
            $state = 1;
            if ($integral_exchange < 0) {
                if ($integral_exchange + $integral < 0) {
                    return ['code' => 500, 'msg' => '用户积分不足'];
                }
            } elseif ($integral_exchange > 0) {
                $state = 0;
            }
            $date_time = intval(date('Ymd', time()));
            $date_time_ukey = $uid . '_' . intval(date('YmdHi', time())) . '_' . $integral_exchange;
            $intelog = IntegralLog::where('ukey', $date_time_ukey)->field('id')->find();
            if (empty($intelog['id'])) {
                $insertData = new IntegralLog();
                $insert_data = [
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $integral_exchange,
                    'score' => 0,
                    'ukey' => $date_time_ukey,
                    'mktime' => time(),
                    'inte_rate' => $inte_rate,
                    'card_game_id' => 0,
                    'state' => $state,
                    'type' => $insertType,
                    'tourist' => $user['tourist'],
                    'user_ai' => $user['ai'],
                    'tid' => $tid
                ];
                $insert_id = $insertData->insert($insert_data);
                if ($insert_id > 0) {               
                     $insert_date_id = IntegralDate::change($uid, $integral_exchange, $tid);                                         
                    if ($insert_date_id == 0) {
                        return ['code' => 500, 'msg' => '操作失败，请过一分钟后再试0'];
                    }else{
                        $lastIntegral = common::math_add($integral, $integral_exchange, 2);
                        $score = common::math_mul($integral_exchange, $inte_rate,2);
                        //写日志
                        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
                        $address = "";                    
                        $note = '代理' . $agents['account'] . '给会员' . $user['username'] . '下积分,积分由' . $integral . '改为' . round(($integral + $integral_exchange), 2);
                        common::system_log($agents['account'], $user['username'], 8, $note, $real_ip, $address, $tid);
                        return ['code' => 200, 'msg' => '操作成功', 'integral' => $lastIntegral,'score'=>-$score];
                     }
                } else {
                    return ['code' => 500, 'msg' => '操作失败，请过一分钟后再试1'];
                }
            } else {
                return ['code' => 500, 'msg' => '操作失败，请过一分钟后再试2'];
            }
        } else {
            return ['code' => 500, 'msg' => '参数错误'];
        }
    }


    /**
     * @function 积分同步
     */
    public function user_integral()
    {
        $datas = IntegralDate::field('uid')->group('uid')->select();
        foreach ($datas as $item) {
            $uid = $item['uid'];
            $intgral = IntegralDate::getUserIntegral(['uid' => $uid]);
            User::where('uid', $uid)->update(['integral' => $intgral]);
            echo '会员:' . $uid . '  剩余积分：' . $intgral . PHP_EOL;
        }
        echo '执行完毕';;
    }

    /**
     * @function 代理积分
     */
    public function agents_integral()
    {
        $data = IntegralLog::alias('inte')->join('user u', 'inte.uid=u.uid')
            ->where('inte.tongji_state', 0)->where('inte.tourist', 0)->where('inte.user_ai', 0)->where('inte.integral', '<', 0)
            ->field('inte.id,inte.integral,inte.mktime,inte.inte_rate,inte.type,u.uid,u.agents_id,u.name')
            ->select();
        if (!empty($data)) {
            $do_agent_profit = FALSE;
            $mktime = time();
            $date_time = date('Ymd');
            foreach ($data as $value) {
                $uid = $value['uid'];
                $user_name = $value['name'];
                $user_xm_rate = $value['inte_rate'];
                if ($value['type'] == 104) {
                    $user_xm_rate = $user_xm_rate + 1;
                }
                if ($value['type'] == 103) {
                    $do_agent_profit = TRUE;
                }
                $integral = abs($value['integral']);
                $agents_id = $value['agents_id'];
                $integral_log_id = $value['id'];
                $mktime = $value['mktime'];
                $boss_id = $this->agent_share($uid, $user_name, $user_xm_rate, $user_xm_rate, $integral, $integral_log_id, $mktime, $agents_id, 1);
                if ($boss_id >= 0) {
                    IntegralLog::where('id', $integral_log_id)->update(['tongji_state' => 1]);
                    echo date('Y-m-d H:i:s') . '|' . 'integral_log_id:' . $integral_log_id . '执行成功' . PHP_EOL;
                } else {
                    echo '执行失败';
                    return false;
                }
            }
            if ($do_agent_profit) {
                $tongji_state = 1;
                //获取当前统计状态值
                $agentsIntegral = AgentsIntegralLog::where('tongji_state', '>', 0)->order('id', 'desc')->field('tongji_state')->find();
                if (!empty($agentsIntegral['tongji_state'])) {
                    $tongji_state = $agentsIntegral['tongji_state'] + 1;
                }
                //结算代理收益
                $agents = Agents::where('agent_profit', '>', 0)->field('agents_id,account,name,agent_profit,agent_score,xm_rate')->select();
                //代理收益结算总额度
                $agent_upfen_total = 0;
                foreach ($agents as $value) {
                    $agents_id = $value['agents_id'];
                    $agents_account = $value['account'];
                    $agent_profit = $value['agent_profit'];
                    $agent_score = $value['agent_score'];
                    $agents_name = $value['name'];
                    $agent_score_after = common::math_add($agent_profit, $agent_score, 2);
                    $insert_data = [
                        'agents_id' => $agents_id,
                        'agents_account' => $agents_account,
                        'agents_name' => $agents_name,
                        'score' => $agent_score,
                        'score_change' => $agent_profit,
                        'score_after' => $agent_score_after,
                        'type' => 13,
                        'mktime' => $mktime,
                        'orderid' => 0,
                        'ukey' => 13 . '_' . $agents_id . '_' . date('YmdHis') . '_profit',
                        'note' => '代理收益结算，额度从' . $agent_score . '改为' . $agent_score_after,
                        'do_agents_account' => '',
                        'do_agents_name' => '',
                        'do_agents_id' => '',
                    ];
                    AgentScoreLog::insert($insert_data, false, true);
                    if ($agents_id > 1) {
                        $agent_upfen_total += $agent_profit;
                        Agents::where('agents_id', $agents_id)->update(['agent_score' => $agent_score_after, 'agent_profit' => 0]);
//                        //插入提取收益流水
//                        $agents_share_data = [
//                            'agents_id' => $agents_id,
//                            'agents_account' => $agents_account,
//                            'agents_name' => $agents_name,
//                            'agents_xm_rate' => $value['xm_rate'],
//                            'agents_integral' => -$agent_profit,
//                            'uid' => 0,
//                            'user_name' => '',
//                            'user_xm_rate' => 0,
//                            'integral' => 0,
//                            'level' => 0,
//                            'mktime' => $mktime,
//                            'agents_relation' => '',
//                            'relation_link' => '',
//                            'ukey' => $agents_id . '_' . $date_time . '_yjjs',
//                            'agent_profit_after' => 0,
//                            'type' => 1
//                        ];
//                        Db::name('agents_integral_log')->insert($agents_share_data, false, true);
                    }
                    AgentsIntegralLog::where('tongji_state', 0)->where('agents_id', $agents_id)->update(['tongji_state' => $tongji_state]);
                }

                if ($agent_upfen_total > 0) {
                    //获取总代理信息
                    $boss_info = Agents::where('agents_id', 1)->field('account,name,agent_score')->find();
                    $boss_score_after = $boss_info['agent_score'] - $agent_upfen_total;
                    //代理余分变动
                    $agents_score_data = [
                        'uid' => 0,
                        'name' => '',
                        'agents_id' => 1,
                        'agents_name' => $boss_info['name'],
                        'agents_account' => $boss_info['account'],
                        'do_agents_id' => 1,
                        'do_agents_name' => $boss_info['name'],
                        'do_agents_account' => $boss_info['account'],
                        'score' => $boss_info['agent_score'],
                        'score_change' => -$agent_upfen_total,
                        'score_after' => $boss_score_after,
                        'type' => 12,
                        'orderid' => $date_time,
                        'ukey' => $date_time . '_profit_count',
                        'mktime' => $mktime,
                        'note' => $date_time . '-代理收益结算，代理上分总和',
                    ];
                    $agents_score_log_id = AgentScoreLog::insert($agents_score_data, false, true);
                    if ($agents_score_log_id > 0) {
                        Agents::where('agents_id', 1)->update(['agent_score' => $boss_score_after]);
                    }
                }
                echo date('Y-m-d H:i:s') . '|代理收益结算完成' . PHP_EOL;
            }
        } else {
            echo date('Y-m-d H:i:s') . '|暂无数据' . PHP_EOL;
        }
    }


    /**
     * @function 代理积分分成
     */
    function agent_share($uid, $user_name, $user_xm_rate, $xm_rate, $integral, $integral_log_id, $mktime, $agents_id, $level, $agents_relation = [], $relation_link = '')
    {
        //获取代理信息
        $agents = Agents::where('agents_id', $agents_id)->field('account,name,xm_rate,boss_id,agent_profit')->find();
        $agents_xm_rate = intval($agents['xm_rate']);
        $agents_account = $agents['account'];
        $agents_name = $agents['name'];
        $boss_id = $agents['boss_id'];
        $agent_profit = $agents['agent_profit'];
        $agents_relation[] = [
            'agents_account' => $agents_account,
            'agents_name' => $agents_name,
            'agents_id' => $agents_id,
            'agents_xm_rate' => $agents_xm_rate
        ];
        $relation_link .= $agents_name . '(' . $agents_account . ')' . ',' . $agents_xm_rate;
        $insert_relation_link = $relation_link;
        $agents_integral = ($agents_xm_rate - $xm_rate) * $integral;
        if ($agents_integral < 0) {
            return $boss_id;
        }
        $agent_profit = $agent_profit + $agents_integral;
        $agents_share_data = [
            'agents_id' => $agents_id,
            'agents_account' => $agents_account,
            'agents_name' => $agents_name,
            'agents_xm_rate' => $agents_xm_rate,
            'agents_integral' => $agents_integral,
            'uid' => $uid,
            'user_name' => $user_name,
            'user_xm_rate' => $user_xm_rate,
            'integral' => $integral,
            'level' => $level,
            'mktime' => $mktime,
            'agents_relation' => json_encode($agents_relation),
            'relation_link' => $insert_relation_link,
            'ukey' => $uid . '_' . $integral_log_id . '_' . $agents_id . '_' . $level,
            'agent_profit_after' => $agent_profit
        ];
        Db::name('agents_integral_log')->insert($agents_share_data, false, true);
        Agents::where('agents_id', $agents_id)->update(['agent_profit' => $agent_profit]);
        if ($boss_id == 0) {
            return $boss_id;
        } else {
            $relation_link .= '->';
            $level++;
            return $this->agent_share($uid, $user_name, $user_xm_rate, $agents_xm_rate, $integral, $integral_log_id, $mktime, $boss_id, $level, $agents_relation, $relation_link);
        }
    }

}