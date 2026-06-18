<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/2/1
 * Time: 10:21 PM
 */

namespace app\index\controller;

use app\index\model\Agents;
use app\index\model\LosewinDay;
use app\index\model\User;
use app\index\model\UserScoreLog;
use app\index\model\BetsMerge;
use app\index\model\Wxagents;
use think\facade\Cache;
use think\facade\Request;
use app\index\common;

class BoardManage
{
    /**
     * @function 查询代理，会员，有效会员
     */
    public function listAgentsUser()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent_auth_type = common::checkAuthType($agents_id);
        $begin_time = mktime(0, 0, 0, date('m') - 2, 1, date('Y'));
        //查询代理总数
        if ($agent_auth_type == 1) {
            $agentsCount = Agents::field('agents_id')->where("auth_type!=1")->count();
            $userCount = User::where('ai', 0)->where('tourist', 0)->count();
            //有效会员最近三个月有下注记录，连user去掉游客，机器人
            $userRealCount = LosewinDay::where('mktime', '>=', $begin_time)->group('uid')->count();
        } else if ($agent_auth_type == 0) {
            $agentsCount = Wxagents::field('agents_id')->where("boss_id", $agents_id)->count();
            $sql = User::alias('u')->join('wxagents wx', 'wx.agents_id=u.agents_id');
            $sql->where('wx.boss_id', $agents_id);
            $sql->where('u.ai', 0)->where('u.tourist', 0);
            $userCount = $sql->count();
            $quer_sql = LosewinDay::alias('lw')->join('wxagents wx', 'lw.agents_id=wx.agents_id');
            $quer_sql->where('lw.mktime', '>=', $begin_time)->where('wx.boss_id', $agents_id);
            $userRealCount = $quer_sql->group('lw.uid')->count();
        }
        $data = [];
        $data['agentsCount'] = $agentsCount;
        $data['userCount'] = $userCount;
        $data['userRealCount'] = $userRealCount;
        return ['code' => 200, 'msg' => '操作成功',
            'data' => $data
        ];
    }

    /**
     * @function 查询会员累计数据
     */
    public function listUserWinLostRank()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $database = config('database.database');
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $type = $request->post('type');
//        $key = date('Ymd') . '_listUserWinLostRank_' . $database . $begin_time . $end_time . $agents_id . $type;
//        if (Cache::get($key)) {
//            $data = json_decode(Cache::get($key), TRUE);
//        } else {
        $upfen = [];
        $downfen = [];
        $winfen = [];
        $losefen = [];
        $xmall = [];
        //累计输赢
        if ($type == 1 or $type == 5) {
            $winfensql = LosewinDay::alias('l')->join('wxagents wx', 'wx.agents_id=l.agents_id');
            $winfensql->where('wx.boss_id', $agents_id);
            if (!empty($begin_time)) {
                $begin_time_sql = date('Ymd', $begin_time);
                $winfensql->where('l.datetime', '>=', $begin_time_sql);
            }
            if (!empty($end_time)) {
                $end_time_sql = date('Ymd', $end_time);
                $winfensql->where('l.datetime', '<=', $end_time_sql);
            }
            if ($type == 1) {
                $winfensql->order('number desc');
            } elseif ($type == 5) {
                $winfensql->order('number asc');
            }
            $betsData = $winfensql->field('sum(l.losewin) as number,l.uid,l.nickname')
                ->group('l.uid')->limit(50)->select();
            if ($type == 1) {
                $winfen = $betsData;
            } elseif ($type == 5) {
                $losefen = $betsData;
            }
        }
        //上下分
        if ($type == 2 or $type == 3) {
            $upfensql = UserScoreLog::alias('u')->join('wxagents wx', 'u.agents_id=wx.agents_id');
            $upfensql->where('wx.boss_id', $agents_id);
            $upfensql->where('u.user_ai', 0)->where('u.tourist', 0);
            $upfensql->field('sum(u.score_change) as fen,u.uid')->group('u.uid')->limit(50);
            if ($type == 2) {
                $upfensql->where('u.type', 11)->order('fen desc');
            } elseif ($type == 3) {
                $upfensql->where('u.type', 12)->order('fen asc');
            }
            if (!empty($begin_time)) {
                $upfensql->where('u.time', '>=', $begin_time);
            }
            if (!empty($end_time)) {
                $upfensql->where('u.time', '<=', $end_time);
            }
            $upfenData = $upfensql->select();

            $uidArr = [];
            $fenData = [];
            $updowfen = [];
            foreach ($upfenData as $value) {
                $uid = $value['uid'];
                $uidArr[] = $uid;
                $fenData[$uid . '_key'] = [
                    'uid' => $uid,
                    'number' => $value['fen'],
                    'nickname' => ''
                ];
            }
            $user = User::whereIn('uid', $uidArr)->field('uid,name')->select();
            foreach ($user as $item) {
                $uid = $item['uid'];
                $fenData[$uid . '_key']['nickname'] = $item['name'];
            }
            foreach ($fenData as $v) {
                $updowfen[] = $v;
            }
            if ($type == 2) {
                $upfen = $updowfen;
            } elseif ($type == 3) {
                $downfen = $updowfen;
            }
        }
        //累计积分
        if ($type == 4) {
            $xmsql = LosewinDay::alias('l')->join('wxagents wx', 'wx.agents_id=l.agents_id');
            $xmsql->where('wx.boss_id', $agents_id);
            if (!empty($begin_time)) {
                $begin_time_sql = date('Ymd', $begin_time);
                $xmsql->where('l.datetime', '>=', $begin_time_sql);
            }
            if (!empty($end_time)) {
                $end_time_sql = date('Ymd', $end_time);
                $xmsql->where('l.datetime', '<=', $end_time_sql);
            }
            $xmsql->field('sum(l.integral) as number,l.nickname,l.uid');
            $xmall = $xmsql->group('l.uid')->order('number desc')->limit(50)->select();
        }
        $data = [];
        $data['upfen'] = $upfen;
        $data['downfen'] = $downfen;
        $data['winfen'] = $winfen;
        $data['losefen'] = $losefen;
        $data['xmall'] = $xmall;
//            Cache::set($key, json_encode($data), 86400);
//        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data
        ];
    }

    /**
     * @function 用户输赢统计
     */

    public function listshuyinguser()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        // if (common::checkEmp($agents_id) != 3 && common::checkEmp($agents_id) != 4) {
        //     return ['code' => 500, 'msg' => '没权限操作'];
        // }
        //输赢用户
        $request = Request::instance();
        $type = $request->post('type');
        if ($type == 1) {//天
            $date_type = '%Y-%m-%d';
        } else if ($type == 2) {//周
            $date_type = '%Y-%u';
        } else if ($type == 3) {//月
            $date_type = '%Y-%m';
        }
        $addusersql = BetsMerge::alias('b')->join('wxagents wx', 'b.agents_id = wx.agents_id');
        $addusersql->where('wx.boss_id', $agents_id);
        $adduser = $addusersql->field("FROM_UNIXTIME(b.mktime, '" . $date_type . "') as Hour,sum(b.win) as Count")->group('Hour')->order('Hour asc')->select();
        $data = [];
        $data['adduser'] = $adduser;
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data
        ];
    }

    /**
     * @function 查询会员上分总额统计
     */
    public function upfenlistchat()
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
        $type = $request->post('type');
        if ($type == 1) {
            $date_type = '%Y-%m-%d';
        } else if ($type == 2) {
            $date_type = '%Y-%u';
        } else if ($type == 3) {
            $date_type = '%Y-%m';
        }
        $upfensql = UserScoreLog::alias('u')->join('wxagents wx', 'u.agents_id = wx.agents_id');
        $upfensql->where('u.type', 11)->where('u.user_ai', 0)->where('u.tourist', 0);
        $upfensql->where('wx.boss_id', $agents_id);
        $upfen = $upfensql->field("FROM_UNIXTIME(u.time, '" . $date_type . "') as Hour,sum(score_change) as Count")
            ->group('Hour')->order('Hour asc')->select();
        $data = [];
        $data['upfen'] = $upfen;
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data
        ];
    }

    /**
     * @function 查询会员下分总额统计
     */
    public function downfenlistchat()
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
        $type = $request->post('type');
        if ($type == 1) {
            $date_type = '%Y-%m-%d';
        } else if ($type == 2) {
            $date_type = '%Y-%u';
        } else if ($type == 3) {
            $date_type = '%Y-%m';
        }
        $upfensql = UserScoreLog::alias('u')->join('wxagents wx', 'u.agents_id = wx.agents_id');
        $upfensql->where('u.type', 12)->where('u.user_ai', 0)->where('u.tourist', 0);
        $upfensql->where('wx.boss_id', $agents_id);
        $upfen = $upfensql->field("FROM_UNIXTIME(u.time, '" . $date_type . "') as Hour,sum(score_change) as Count")
            ->group('Hour')->order('Hour asc')->select();
        $data = [];
        $data['downfen'] = $upfen;
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data
        ];
    }

    /**
     * @function 查询会员洗码总额统计
     */
    public function xmlistchat()
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
        $type = $request->post('type');
        if ($type == 1) {
            $date_type = '%Y-%m-%d';
        } else if ($type == 2) {
            $date_type = '%Y-%u';
        } else if ($type == 3) {
            $date_type = '%Y-%m';
        }
        $xmsql = BetsMerge::alias('b')->join('wxagents wx', 'b.agents_id = wx.agents_id');
        $xmsql->where('wx.boss_id', $agents_id);
        $xm = $xmsql->field("Round(sum(user_zx_xm+user_sb_xm+user_lucky_xm)/1000,2) as Count,FROM_UNIXTIME(b.mktime, '" . $date_type . "') as Hour")
            ->group('Hour')->order('Hour asc')->select();
        $data = [];
        $data['xm'] = $xm;
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data
        ];
    }


}