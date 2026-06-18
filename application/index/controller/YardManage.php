<?php
/**
 * @function 结算码粮
 *
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/7/4
 * Time: 12:58 PM
 */

namespace app\index\controller;

use app\index\model\IntegralDate;
use app\index\common;
use app\index\model\User;
use app\index\model\IntegralLog;
use app\index\model\UserScoreLog;
use app\index\model\Yard;
use think\facade\Request;
use app\index\model\Agents;
use app\index\model\AgentScoreLog;

class YardManage
{
    /**
     * @function 结算码粮
     */
    public function count_yard()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $user_upfen_total = 0;//结算码粮，上分总和
        $date_time = date('Ymd');
        $mktime = time();
        $uidRes = IntegralDate::alias('inte')->field('inte.uid')
            ->join('user u', 'inte.uid=u.uid')
            ->where('inte.date_time', '<=', $date_time)->where('u.tourist', 0)->where('u.ai', 0)
            ->where('inte.tid', $tid)
            ->group('inte.uid')->select();
        foreach ($uidRes as $item) {
            $uid = $item['uid'];
            //获取剩余积分
            $integral_total = IntegralDate::getUserIntegral(['uid' => $uid]);
            $integral_total_exchange = floor($integral_total);
            if ($integral_total_exchange > 0) {
                //获取余分
                $user_info = User::field('score,xm_rate,name,csf')->where('uid', $uid)->find();
                $score = $user_info['score'];
                $inte_rate = $user_info['xm_rate'];
                $nickname = $user_info['name'];
                $exchange_score = $integral_total_exchange * $inte_rate;
                $score_after = $score + $exchange_score;
                $csf = $user_info['csf'] + $exchange_score;
                $integral_after = 0;
                //扣除积分
                $integral_exchange = -$integral_total_exchange;
                $state = 1;
                $date_time = intval(date('Ymd', $mktime));
                $date_time_ukey = $uid . '_' . $date_time . '_' . $integral_exchange . '_' . $mktime;
                $insert_data = [
                    'uid' => $uid,
                    'date_time' => $date_time,
                    'integral' => $integral_exchange,
                    'score' => 0,
                    'ukey' => $date_time_ukey,
                    'mktime' => $mktime,
                    'inte_rate' => $inte_rate,
                    'card_game_id' => 0,
                    'state' => $state,
                    'type' => 103,//结算码粮
                    'tid' => $tid
                ];
                $insert_id = IntegralLog::insert($insert_data, false, true);
                if ($insert_id > 0) {
                    $insert_data_id = IntegralDate::change($uid, $integral_exchange, $tid);
                    if ($insert_data_id) {
                        $insert_data_arr = [
                            'uid' => $uid,
                            'score_before' => $score,
                            'integral_before' => $integral_total_exchange,
                            'integral_rate' => $inte_rate,
                            'score_after' => $score_after,
                            'integral_after' => $integral_after,
                            'mktime' => $mktime,
                            'ukey' => $date_time . '_' . $uid . '_' . $mktime,
                            'exchange_score' => $exchange_score,
                            'tid' => $tid
                        ];
                        $yard_insert_id = Yard::insert($insert_data_arr, false, true);
                        if ($yard_insert_id) {
                            $insert_score_log = [
                                'uid' => $uid,
                                'score' => $score,
                                'score_change' => $exchange_score,
                                'type' => 100,
                                'time' => $mktime,
                                'card_game_id' => 0,
                                'state' => 0,
                                'user_ai' => 0,
                                'tourist' => 0,
                                'orderid' => $yard_insert_id,
                                'score_after' => $score_after,
                                'ukey' => $yard_insert_id . '_100' . '_' . $uid,
                                'note' => '码粮结算，上分' . $exchange_score,
                                'tid' => $tid,
                                'agents_id' => $tid
                            ];
                            $score_log_id = UserScoreLog::insert($insert_score_log, false, true);
                            if ($score_log_id > 0) {
                                if ($exchange_score > 0) {
                                    $user_upfen_total += $exchange_score;
                                    $res = User::where('uid', $uid)->update(['score' => $score_after, 'csf' => $csf]);
                                    if ($res != 1) {
                                        common::write_log('用户 ' . $uid . ' 余分修改失败,请联系技术');
                                        //return ['code' => 500, 'msg' => '用户 ' . $uid . ' 余分修改失败,请联系技术'];
                                    }
                                }
                            } else {
                                common::write_log('用户 ' . $uid . ' 余分流水插入失败,请联系技术');
                                //return ['code' => 500, 'msg' => '用户 ' . $uid . ' 余分流水插入失败,请联系技术'];
                            }
                        } else {
                            common::write_log('用户 ' . $uid . ' 码粮结算记录插入失败,请联系技术');
                            //return ['code' => 500, 'msg' => '用户 ' . $uid . ' 码粮结算记录插入失败,请联系技术'];
                        }
                    } else {
                        common::write_log('用户 ' . $uid . ' 积分扣除失败,请联系技术');
                        //return ['code' => 500, 'msg' => '用户 ' . $uid . ' 积分扣除失败,请联系技术'];
                    }
                } else {
                    common::write_log('用户 ' . $uid . ' 积分流水插入失败,请联系技术');
                    //return ['code' => 500, 'msg' => '用户 ' . $uid . ' 积分流水插入失败,请联系技术'];
                }
            }
        }
        $agents = Agents::where('agents_id', $agents_id)->field('account,name')->find();
        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
        $ip_data = json_decode($ip_data_json, true);
        $c_ip = $real_ip;
        $address = "";
        if (!empty($ip_data) && $ip_data['code'] == 0) {
            $ip_info = $ip_data['data'];
            $c_ip = $ip_info['ip'];
            $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
        }
        $note = '代理' . $agents['account'] . '结算码粮';
        common::system_log($agents['account'], "-", 12, $note, $c_ip, $address, $tid);
        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function 结算列表
     */
    public function yardList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_account = $request->post('agents_account');
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sumData = [
            'exchange_integral_all' => 0,
            'exchange_score' => 0,
        ];
        $sqlAll = Yard::alias('y')->field('y.integral_rate,y.uid,y.mktime,y.integral_before as integral_exchange ,y.exchange_score,u.name,u.agents_account,u.agents_name');
        $sqlAll->join('user u', 'y.uid=u.uid');
        $sqlAll->order('y.id', 'desc');
        $sqlAll->where('y.tid', $tid);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sqlAll->where('y.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sqlAll->where('y.mktime', '<=', $end_time_sql);
        }
        if (!empty($uid)) {
            $sqlAll->where('y.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sqlAll->where('u.agents_account', $agents_account);
        }
        $dataAll = $sqlAll->select();
        foreach ($dataAll as &$item) {
            $item['mktime'] = date('Y-m-d H:i:s', $item['mktime']);
            $sumData['exchange_integral_all'] += $item['integral_exchange'];
            $sumData['exchange_score'] += $item['exchange_score'];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $dataAll,
                'sumData' => $sumData,
            ]];
    }


    /**
     * @function 结算列表报表
     */
    public function getYardExport()
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
        $uid = $request->post('uid');
        $agents_account = $request->post('agents_account');
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sumData = [
            'exchange_integral_all' => 0,
            'exchange_score' => 0,
        ];
        $sqlAll = Yard::alias('y')->field('y.integral_rate,y.uid,y.mktime,y.integral_before as integral_exchange ,y.exchange_score,u.name,u.agents_account,u.agents_name');
        $sqlAll->join('user u', 'y.uid=u.uid');
        $sqlAll->order('y.id', 'desc');
        $sqlAll->where('y.tid', $tid);
        if ($agents_id != 1) {
            $sqlAll->where('u.agents_id', 'IN', function ($query) use ($agents_id) {
                $query->table('wxagents')->where('boss_id', $agents_id)->field('agents_id');
            });
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime(substr($request->post('begin_time'), 0, 34));
            $sqlAll->where('y.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime(substr($request->post('end_time'), 0, 34));
            $sqlAll->where('y.mktime', '<=', $end_time_sql);
        }
        if (!empty($uid)) {
            $sqlAll->where('y.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sqlAll->where('u.agents_account', $agents_account);
        }
        $dataAll = $sqlAll->select();
        foreach ($dataAll as &$item) {
            $item['mktime'] = date('Y-m-d H:i:s', $item['mktime']);
            $sumData['exchange_integral_all'] += $item['integral_exchange'];
            $sumData['exchange_score'] += $item['exchange_score'];
        }
        $excelData = [["结算积分总数量", $sumData['exchange_integral_all'], "上分总额度", $sumData['exchange_score']]];
        array_push($excelData, ["用户ID", "用户昵称", "结算积分", "积分比例", "上分额度", "时间"]);
        foreach ($dataAll as $key => $value) {
            $insert = [];
            array_push($insert, $value['uid']);
            array_push($insert, $value['name']);
            array_push($insert, $value['integral_exchange']);
            array_push($insert, $value['integral_rate']);
            array_push($insert, $value['exchange_score']);
            array_push($insert, $value['mktime']);
            array_push($excelData, $insert);
        }

        //写excel
        if (!empty($begin_time)) {
            $title = "码粮结算" . date("Y-m-d", strtotime(substr($request->post('begin_time'), 0, 34))) . '-' . date("Y-m-d", strtotime(substr($request->post('end_time'), 0, 34)));
        } else {
            $title = "码粮结算-全部";
        }
        common::exportExcel($title, "码粮结算", "xls", $excelData);

        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $dataAll,
                'sumData' => $sumData,
            ]];
    }


    /**
     * @function 下载excel表格
     */
    public function doExcel()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $uid = $request->get('uid');
        $sql = new Yard();
        $sql->field('id,score_before,integral_before,integral_rate,uid,score_after,integral_after,mktime,ukey,exchange_score,nickname');
        $sql->order('id', 'desc');
        $sql->where('tid', $tid);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('mktime', '<=', $end_time_sql);
        }
        if (!empty($uid)) {
            $sql->where('uid', $uid);
        }
        $datas = $sql->select();
        $count = 0;// 查询满足要求的总记录数
        foreach ($datas as $item) {
            $count++;
        }
        $title_str = "用户ID,昵称,结算前余分,提取的积分,积分兑换比例,兑换额度,结算后余分,结算后积分";
        if ($count == 0) {
            $exl_title = explode(',', $title_str);
            common::exportToExcel('结算码粮报表.xls', $exl_title, [], 100);
            exit();
        }
        $kkk = 1;
        $rs[$kkk] = $datas;
        $title_str[$kkk] = $title_str;
        $exl_title[$kkk] = explode(',', $title_str);
        foreach ($rs[$kkk] as $k => $v) {
            $exl[$kkk][] = array(
                $v['uid'],
                $v['nickname'],
                $v['score_before'],
                $v['integral_before'],
                $v['integral_rate'],
                $v['exchange_score'],
                $v['score_after'],
                $v['integral_after'],
            );
        }
        common::exportToExcel('结算码粮报表.xls', $exl_title[$kkk], $exl[$kkk], 1000);
        exit();
    }
}
