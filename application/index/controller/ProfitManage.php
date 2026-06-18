<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/1/24
 * Time: 8:25 AM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\AgentScoreLog;
use app\index\model\BetsMerge;
use app\index\model\CardGameReport;
use app\index\model\IntegralLog;
use app\index\model\LhCardGameReport;
use app\index\model\NnCardGameReport;
use app\index\model\Profit;
use app\index\model\User;
use app\index\model\Agents;
use app\index\model\UserScoreLog;
use app\index\model\ZjhCardGameReport;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Domain;

class ProfitManage
{
    public function __construct()
    {
        common::checkLogin();
    }


    /**
     * @function 盈亏
     */
    public function winlose()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_time_post = $request->post('begin_time');
        $end_time_post = $request->post('end_time');
        $profit_mktime = Profit::field('mktime')->where('tid', $tid)->order('id', 'desc')->limit(1)->find();
        $begin_date = date('Y-m-d');
        $begin_time = strtotime(date('Y-m-d'));
        if (!empty($profit_mktime)) {
            $begin_date = $profit_mktime['mktime'];
            $begin_time = strtotime($profit_mktime['mktime']);
        }
        if (!empty($begin_time_post)) {
            $begin_date = $begin_time_post;
            $begin_time = strtotime($begin_time_post);
        }
        $end_date = date('Y-m-d H:i:s');
        $end_time = time();
        if (!empty($end_time_post)) {
            $end_date = $end_time_post;
            $end_time = strtotime($end_time_post);
        }
        $upfen_total = 0; //会员上分总额
        $dowfen_total = 0; //会员下分总额
        $agent_upfen_total = 0; //代理上分总额
        $agent_dowfen_total = 0; //代理下分总额
        $integral_exchange_total = 0; //积分兑换总额
        $user_score_total = 0;//用户总余分
        $user_win_lose = 0;//用户输赢数
        $user_original_score = 0;//用户初始分
        $user_integral = 0;//用户剩余积分
        $agent_score_total = 0;//代理余分总额
        $all_score_total = 0;//总余分
        //上下分总额
        $agentScoreLog = UserScoreLog::where('time', '>=', $begin_time)->where('time', '<=', $end_time)
            ->where('tid', $tid)->whereIn('type', [11, 12])->where('user_ai', 0)->where('tourist', 0)
            ->field('score_change,type')->select();
        foreach ($agentScoreLog as $value) {
            if ($value['type'] == 11) {
                $agent_upfen_total = common::math_add($agent_upfen_total, $value['score_change'], 0);
            }
            if ($value['type'] == 12) {
                $agent_dowfen_total = common::math_add($agent_dowfen_total, $value['score_change'], 0);
            }
        }
        //积分兑换总额
        $integralLog = IntegralLog::where('mktime', '>=', $begin_time)
            ->where('mktime', '<=', $end_time)->where('integral', '<', 0)->where('user_ai', 0)->where('tourist', 0)
            ->where('tid', $tid)
            ->field('inte_rate,integral')->select();
        foreach ($integralLog as $value) {
            $integral_exchange_total = common::math_add($integral_exchange_total, $value['inte_rate'] * $value['integral']);
        }
        //用户总余分
        $user_score = User::where('ai', 0)->where('tourist', 0)->where('tid', $tid)->field('integral,score,csf')->select();
        foreach ($user_score as $value) {
            $user_score_total = common::math_add($user_score_total, $value['score'], 2);
            $user_integral = common::math_add($user_integral, intval($value['integral']), 2);
            $user_original_score = common::math_add($user_original_score, $value['csf'], 2);
        }
        
        // $user_win_lose = BetsMerge::where('profit_guiling', 0)->sum('win');
        $bjl_user_win_lose = CardGameReport::where('guiling', 0)->where('tid', $tid)->where('mktime', '>=', $begin_time)
        ->where('mktime', '<=', $end_time)->sum('khyk');
        $bjl_zc_win_lose = CardGameReport::where('guiling', 0)->where('mktime', '>=', $begin_time)
        ->where('mktime', '<=', $end_time)->where('tid', $tid)->sum('zxyk_zc');
        $lh_user_win_lose = LhCardGameReport::where('guiling', 0)->where('mktime', '>=', $begin_time)
            ->where('mktime', '<=', $end_time)->where('tid', $tid)->sum('khyk');
        $user_win_lose = $bjl_user_win_lose + $lh_user_win_lose - $bjl_zc_win_lose;
        if ($tid == 262) {
            $user_original_score = sprintf("%.2f", $user_score_total - $user_win_lose);
        }
        //代理余分总额
        //  $agent_score_total = Agents::where('agents_id', '>', 1)->where('tid', $tid)->sum('agent_score');

        $all_upfen_total = common::math_add($upfen_total, $agent_upfen_total, 2);
        $all_dowfen_total = common::math_add($dowfen_total, $agent_dowfen_total, 2);
        $all_score_total = common::math_add($agent_score_total, $user_score_total, 2);
        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => [
                    'begin_date' => $begin_date,
                    'end_date' => $end_date,
                    'upfen_total' => $all_upfen_total,
                    'dowfen_total' => $all_dowfen_total,
                    'integral_exchange_total' => $integral_exchange_total,
                    'user_score_total' => $user_score_total,
                    'user_win_lose' => sprintf("%.2f", $user_win_lose),
                    'user_original_score' => $user_original_score,
                    'user_integral' => $user_integral,
                    'agent_score_total' => $agent_score_total,
                    'all_score_total' => $all_score_total
                ]
            ],
        ];

    }


    /**
     * @function 输赢归零
     */
    public function profitGuiling()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_date = $request->post('begin_date');
        $end_date = $request->post('end_date');
        $upfen_total = $request->post('upfen_total');
        $dowfen_total = $request->post('dowfen_total');
        $integral_exchange_total = $request->post('integral_exchange_total');
        $user_score_total = $request->post('user_score_total');
        $user_win_lose = $request->post('user_win_lose');
        $user_original_score = $request->post('user_original_score');
        $user_integral = $request->post('user_integral');
        $agent_score_total = $request->post('agent_score_total');
        $all_score_total = $request->post('all_score_total');
        if (common::checkEmp($agents_id) != 3) {
            return ['code' => 500, 'msg' => '没权限操作'];
        }

        BetsMerge::where('profit_guiling', 0)->where('tid', $tid)->update(['profit_guiling' => 1]);
        $insert_id = Profit::create([
            'begin_date' => $begin_date,
            'end_date' => $end_date,
            'upfen_total' => $upfen_total,
            'dowfen_total' => $dowfen_total,
            'integral_exchange_total' => $integral_exchange_total,
            'user_score_total' => $user_score_total,
            'user_win_lose' => $user_win_lose,
            'user_original_score' => $user_original_score,
            'user_integral' => $user_integral,
            'agent_score_total' => $agent_score_total,
            'all_score_total' => $all_score_total,
            'tid' => $tid
        ]);
        $agents = Agents::where('agents_id', $agents_id)->field('account,name')->find();
        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
       // $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
       // $ip_data = json_decode($ip_data_json, true);

        $c_ip = $real_ip;
        $address = "";
        if (!empty($ip_data) && $ip_data['code'] == 0) {
            $ip_info = $ip_data['data'];
            $c_ip = $ip_info['ip'];
            $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
        }

        $note = '代理' . $agents['account'] . '操作输赢归零';
        common::system_log($agents['account'], "-", 13, $note, $real_ip, $address, $tid);
        //修改开工状态
        Domain::where('type',16)->update(['status'=>1]);
        return ['code' => 200, 'msg' => '', 'insertRes' => $insert_id];
    }

    /**
     * @function 历史报表
     */
    public function profitHistory()
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
        $profit = Profit::order('id', 'desc');
        $profit->where('tid', $tid);
        if (!empty($begin_time)) {
            $profit->where('begin_date', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $profit->where('begin_date', '<=', $end_time);
        }
        $data = $profit->select();

        return ['code' => 200, 'msg' => '请求成功', 'data' => ['list' => $data]];
    }


}