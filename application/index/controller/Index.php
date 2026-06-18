<?php

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\AgentsGroup;
use app\index\model\MenuUrl;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Wxuser;
use app\index\model\BetsLog;
use think\Db;
use app\index\model\Domain;
use app\index\model\ChatHistory;
use app\index\model\teamroom;

class Index
{

    /**
     * @function 首页
     */
    public function index()
    {
        return view('dist/index');
    }

    /**
     * @function 我的桌面
     */
    public function welcome()
    {
        $agents_id = Session::get('agents_id');
        $agentsRes = Agents::getAgentsGroup(['agents_id' => $agents_id]);
        $agents = $agentsRes[0];
        $group_level = $agents['group_level'];
        if ($group_level > 1) {
            echo '您没有权限访问。';
            exit();
        }
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $query = Db::name('zy_tongji')->where('deleted', 0)->order('id', 'desc');
        if (!empty($begin_time)) {
            $query->where('mktime', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $query->where('mktime', '<=', $end_time);
        }

        $tongji = $query->select();
        //获取web域名
        $web_domain = '';
        $wsurl = '';
        $domain = Domain::whereIn('type', [6, 12])->field('domain,type')->select();
        foreach ($domain as $item) {
            if ($item['type'] == 6) {
                $web_domain = $item['domain'];
            }
            if ($item['type'] == 12) {
                $wsurl = $item['domain'];
            }
        }

        //上局历史消息
        $chatHistory = ChatHistory::order('id', 'desc')->limit(1)->find();
        $chatHistoryId = 0;
        if (!empty($chatHistory)) {
            $chatHistoryId = intval($chatHistory->id);
        }
        $system = teamroom::selectSystemInfo([], 'fast_msg,counttime');
        if (!empty($system['fast_msg'])) {
            $system['fast_msg'] = explode('+', $system['fast_msg']);
        }
        return view('welcome', [
            'tongji' => $tongji,
            'web_domain' => $web_domain,
            'wsurl'=>$wsurl,
            'chatHistoryId' => $chatHistoryId,
            'system' => $system,
            'parama' => [
                'begin_time' => $begin_time,
                'end_time' => $end_time,
            ]
        ]);
    }

    /**
     * @function  删除自营报表数据
     */
    public function deleted_zy_tongji()
    {
        $request = Request::instance();
        $id = $request->get('id');
        Db::name('zy_tongji')->where(['id' => $id])->update(['deleted' => 1]);
        return ['code' => 200];
    }


    /**
     * @function 下载excel表格
     */
    public function doExcel()
    {
        $agents_id = Session::get('agents_id');
        $agentsRes = Agents::getAgentsGroup(['agents_id' => $agents_id]);
        $agents = $agentsRes[0];
        $group_level = $agents['group_level'];
        if ($group_level > 1) {
            echo '您没有权限访问。';
            exit();
        }
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $query = Db::name('zy_tongji')->where('deleted', 0)->order('id', 'desc');
        if (!empty($begin_time)) {
            $query->where('mktime', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $query->where('mktime', '<=', $end_time);
        }

        $count = $query->count();// 查询满足要求的总记录数
        if ($count == 0) {
            $title_str = "时间,台面本金,台面筹码,尾数盈亏,三宝盈亏,对冲盈亏,代理总赢,庄闲洗码,三宝洗码,客户盈亏,总上分,总下分,用户总余分";
            $exl_title = explode(',', $title_str);
            common::exportToExcel('财务报表' . '.xls', $exl_title, [], 100);
            exit();
        }
        $pageNum = 1000;//每页数量
        $ppp = ceil($count / $pageNum);
        $pp = range(1, $ppp);
        foreach ($pp as $kkk => $vvv) {
            $rs[$kkk] = $query->page($vvv . ", $pageNum")->select();
            $title_str[$kkk] = "时间,台面本金,台面筹码,尾数盈亏,三宝盈亏,对冲盈亏,代理总赢,庄闲洗码,三宝洗码,客户盈亏,总上分,总下分,用户总余分";
            $exl_title[$kkk] = explode(',', $title_str[$kkk]);
            foreach ($rs[$kkk] as $k => $v) {
                if (!$v['mktime']) $v['mktime'] = '暂无数据';
                if (!$v['tmbj']) $v['tmbj'] = '暂无数据';
                if (!$v['tmcm']) $v['tmcm'] = '暂无数据';
                if (!$v['wsyk']) $v['wsyk'] = '暂无数据';
                if (!$v['sbyk']) $v['sbyk'] = '暂无数据';
                if (!$v['dcyk']) $v['dcyk'] = '暂无数据';
                if (!$v['dlzy']) $v['dlzy'] = '暂无数据';
                if (!$v['zxxm']) $v['zxxm'] = '暂无数据';
                if (!$v['sbxm']) $v['sbxm'] = '暂无数据';
                if (!$v['khyk']) $v['khyk'] = '暂无数据';
                if (!$v['upfen_all']) $v['upfen_all'] = '暂无数据';
                if (!$v['dowfen_all']) $v['dowfen_all'] = '暂无数据';
                if (!$v['score_all']) $v['score_all'] = '暂无数据';
                $exl[$kkk][] = array(
                    $v['mktime'], $v['tmbj'], $v['tmcm'], $v['wsyk'], $v['sbyk'], $v['dcyk'], $v['dlzy'], $v['zxxm'],
                    $v['sbxm'], $v['khyk'], $v['upfen_all'], $v['dowfen_all'], $v['score_all']
                );
            }

            common::exportToExcel('财务报表' . $vvv . '.xls', $exl_title[$kkk], $exl[$kkk], $pageNum);
        }
        exit();
    }





//    public function welcome()
//    {
//        $agents_uid = Session::get('agents_uid');
//        $users = Wxuser::getWxUserInfo($agents_uid, $begin_time = '', $end_time = '');
//        $tongji= [
//            'all' => [
//                'fans' => 0,
//                'teams' => 0,
//                'zxxm' => 0,
//                'sbxm' => 0,
//                'upfen' => 0,
//            ],
//            'today' => [
//                'fans' => 0,
//                'teams' => 0,
//                'zxxm' => 0,
//                'sbxm' => 0,
//                'upfen' => 0,
//            ],
//            'yesterday' => [
//                'fans' => 0,
//                'teams' => 0,
//                'zxxm' => 0,
//                'sbxm' => 0,
//                'upfen' => 0,
//            ],
//            'cur_month' => [
//                'fans' => 0,
//                'teams' => 0,
//                'zxxm' => 0,
//                'sbxm' => 0,
//                'upfen' => 0,
//            ],
//            'last_month' => [
//                'fans' => 0,
//                'teams' => 0,
//                'zxxm' => 0,
//                'sbxm' => 0,
//                'upfen' => 0,
//            ],
//        ];
//        $today_date = common::getToday();
//        $today_begin_time = strtotime($today_date[0]);
//        $today_end_time = strtotime($today_date[1]);
//        $yesterday_date = common::getYesterday();
//        $yesterday_begin_time = strtotime($yesterday_date[0]);
//        $yesterday_end_time = strtotime($yesterday_date[1]);
//        $cur_month_date = common::getCurMonth();
//        $cur_month_begin_time = strtotime($cur_month_date[0]);
//        $cur_month_end_time = strtotime($cur_month_date[1]);
//        $last_month_date = common::getLastMonth();
//        $last_month_begin_time = strtotime($last_month_date[0]);
//        $last_month_end_time = strtotime($last_month_date[1]);
//        foreach ($users as $user) {
//            $uid = $user->uid;
//            $betLogsAll = BetsLog::lastRecordAll($uid, $begin_time = '', $end_time = '');
//            foreach ($betLogsAll as $item) {
//                $time = strtotime($item['time']);
//                if (in_array($item['type'], [1, 2])) {
//                    $tongji['all']['zxxm'] += $item['xm'];
//                    if ($time>=$today_begin_time && $time<= $today_end_time){
//                        $tongji['today']['zxxm'] += $item['xm'];
//                    }
//                    if ($time>=$yesterday_begin_time && $time<= $yesterday_end_time){
//                        $tongji['yesterday']['zxxm'] += $item['xm'];
//                    }
//                    if ($time>=$cur_month_begin_time && $time<= $cur_month_end_time){
//                        $tongji['cur_month']['zxxm'] += $item['xm'];
//                    }
//                    if ($time>=$last_month_begin_time && $time<= $last_month_end_time){
//                        $tongji['last_month']['zxxm'] += $item['xm'];
//                    }
//                }
//                if (in_array($item['type'], [3, 4, 5])) {
//                    $tongji['all']['sbxm'] += $item['xm'];
//                    if ($time>=$today_begin_time && $time<= $today_end_time){
//                        $tongji['today']['sbxm'] += $item['xm'];
//                    }
//                    if ($time>=$yesterday_begin_time && $time<= $yesterday_end_time){
//                        $tongji['yesterday']['sbxm'] += $item['xm'];
//                    }
//                    if ($time>=$cur_month_begin_time && $time<= $cur_month_end_time){
//                        $tongji['cur_month']['sbxm'] += $item['xm'];
//                    }
//                    if ($time>=$last_month_begin_time && $time<= $last_month_end_time){
//                        $tongji['last_month']['sbxm'] += $item['xm'];
//                    }
//                }
//            }
//            $reg_time = strtotime($user['reg_time']);
//            if ($user['level'] == 1) {
//                $tongji['all']['fans'] += 1;
//                if ($reg_time>=$today_begin_time && $reg_time<= $today_end_time){
//                    $tongji['today']['fans'] += 1;
//                }
//                if ($reg_time>=$yesterday_begin_time && $reg_time<= $yesterday_end_time){
//                    $tongji['yesterday']['fans'] += 1;
//                }
//                if ($reg_time>=$cur_month_begin_time && $reg_time<= $cur_month_end_time){
//                    $tongji['cur_month']['fans'] += 1;
//                }
//                if ($reg_time>=$last_month_begin_time && $reg_time<= $last_month_end_time){
//                    $tongji['last_month']['fans'] += 1;
//                }
//            }
//
//            $tongji['all']['teams'] += 1;
//            if ($reg_time>=$today_begin_time && $reg_time<= $today_end_time){
//                $tongji['today']['teams'] += 1;
//            }
//            if ($reg_time>=$yesterday_begin_time && $reg_time<= $yesterday_end_time){
//                $tongji['yesterday']['teams'] += 1;
//            }
//            if ($reg_time>=$cur_month_begin_time && $reg_time<= $cur_month_end_time){
//                $tongji['cur_month']['teams'] += 1;
//            }
//            if ($reg_time>=$last_month_begin_time && $reg_time<= $last_month_end_time){
//                $tongji['last_month']['teams'] += 1;
//            }
//            //上下分
//            $moneys_sql = Db::name('user_money_log')->where('uid', $uid)->where('user_ai', 0)->where('tourist', 0);
//            $moneys = $moneys_sql->select();
//            foreach ($moneys as $item) {
//                $upfen_time = strtotime($item['time']);
//                if ($item['state'] == 1) {
//                    if ($item['money_change'] > 0) {
//                        $tongji['all']['upfen'] += $item['money_change'];
//                        if ($upfen_time>=$today_begin_time && $upfen_time<= $today_end_time){
//                            $tongji['today']['upfen'] += $item['money_change'];
//                        }
//                        if ($upfen_time>=$yesterday_begin_time && $upfen_time<= $yesterday_end_time){
//                            $tongji['yesterday']['upfen'] += $item['money_change'];
//                        }
//                        if ($upfen_time>=$cur_month_begin_time && $upfen_time<= $cur_month_end_time){
//                            $tongji['cur_month']['upfen'] += $item['money_change'];
//                        }
//                        if ($upfen_time>=$last_month_begin_time && $upfen_time<= $last_month_end_time){
//                            $tongji['last_month']['upfen'] += $item['money_change'];
//                        }
//                    }
//                }
//            }
//        }
//
//        return view('welcome', ['tongji' => $tongji]);
//    }
}
