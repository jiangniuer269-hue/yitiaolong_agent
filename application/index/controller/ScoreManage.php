<?php

/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/4/1
 * Time: 1:29 PM
 */

namespace app\index\controller;

use app\index\model\AgentScoreLog;
use app\index\model\CardGame;
use app\index\model\UserScoreLog;
use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Wxuser;
use app\index\common;
use app\index\model\BetsMerge;
class ScoreManage
{

    public function __construct()
    {
        common::checkLogin();
    }


    /**
     * @function 余分流水
     */
    public function scoreList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $agents_id = $request->post('agents_id');
        $pageNumber = intval($request->post('pageNumber'));
        $pageSize = intval($request->post('pageSize'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $userId = $request->post('userId');
        $card_game_id = $request->post('card_game_id');
        $data_type = $request->post('data_type');
        $sql = UserScoreLog::alias('m')->join('user u', 'u.uid=m.uid')
            ->where('m.user_ai', 0)->where('m.tourist', 0)
            ->where('m.tid', $tid)
            ->field('u.uid,u.name,m.id,m.score,m.score_change,m.type,m.score_after,m.card_game_id,m.time')
            ->order('m.id', 'desc');
        if (!empty($begin_time)) {
            $sql->where('m.time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('m.time', '<=', $end_time);
        }
        if (!empty($userId)) {
            $sql->where('m.uid', $userId);
        }
        if (intval($card_game_id) > 0) {
            $sql->where('m.card_game_id', $card_game_id);
        }
        if (!empty($data_type)) {
            $sql->where('m.type', $data_type);
        }

        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        return ['code' => 200, 'msg' => '操作成功',
            'data' => $data,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'total' => $total
        ];
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
        $agent_uid = Session::get('agents_uid');
        $fans = Wxuser::getWxUser($agent_uid);
        $fansUid = [];
        foreach ($fans as $item) {
            $fansUid[] = $item->uid;
        }
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $userId = $request->get('userId');
        $card_game_id = $request->get('card_game_id');
        $sql = UserScoreLog::alias('m')->join('user u', 'u.uid=m.uid')->whereIn('u.uid', $fansUid)
            ->where('m.user_ai', 0)->where('m.tourist', 0)->where('orderid', 0)
            ->where('m.tid', $tid)
            ->field('u.uid,u.head,u.name,m.id,m.score,m.score_change,m.type,m.score_after,m.card_game_id,m.time')
            ->order('m.id', 'desc');
        if (!empty($begin_time)) {
            $sql->where('m.time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('m.time', '<=', $end_time);
        }
        if (!empty($userId)) {
            $sql->where('m.uid', $userId);
        }
        if (intval($card_game_id) > 0) {
            $sql->where('m.card_game_id', $card_game_id);
        }
        $seven_day = date('Y-m-d 00:00:00', time() - 3600 * 24 * 1);
        if (empty($begin_time) and empty($end_time)) {
            $sql->where('m.time', '>=', $seven_day);
        }
        $count = $sql->count();// 查询满足要求的总记录数
        if ($count == 0) {
            $title_str = "用户ID,昵称,初始分,变化的分,余分,类型,牌局ID,时间";
            $exl_title = explode(',', $title_str);
            common::exportToExcel('余分流水报表' . '.xls', $exl_title, [], 100);
            exit();
        }
        $pageNum = 1000;//每页数量
        $ppp = ceil($count / $pageNum);
        $pp = range(1, $ppp);
        foreach ($pp as $kkk => $vvv) {
            $sql = UserScoreLog::alias('m')->join('user u', 'u.uid=m.uid')->whereIn('u.uid', $fansUid)
                ->where('m.user_ai', 0)->where('m.tourist', 0)
                ->where('m.tid', $tid)
                ->field('u.uid,u.head,u.name,m.id,m.score,m.score_change,m.type,m.score_after,m.card_game_id,m.time')
                ->order('m.id', 'desc');
            if (!empty($begin_time)) {
                $sql->where('m.time', '>=', $begin_time);
            }
            if (!empty($end_time)) {
                $sql->where('m.time', '<=', $end_time);
            }
            if (!empty($userId)) {
                $sql->where('m.uid', $userId);
            }
            $seven_day = date('Y-m-d 00:00:00');
            if (empty($begin_time) and empty($end_time)) {
                $sql->where('m.time', '>=', $seven_day)->limit(1000);
            }
            $rs[$kkk] = $sql->page($vvv . ", $pageNum")->select();

            $title_str[$kkk] = "用户ID,昵称,初始分,变化的分,余分,类型,牌局ID,时间";
            $exl_title[$kkk] = explode(',', $title_str[$kkk]);
            foreach ($rs[$kkk] as $k => $v) {
                if (!$v['uid']) $v['uid'] = '暂无数据';
                if (!$v['name']) $v['name'] = '暂无数据';
                if (!$v['score']) $v['score'] = '暂无数据';
                if (!$v['score_change']) $v['score_change'] = '暂无数据';
                if (!$v['score_after']) $v['score_after'] = '暂无数据';
                if (!$v['card_game_id']) $v['card_game_id'] = '暂无数据';
                if (!$v['time']) $v['time'] = '暂无数据';
                $v['type'] = common::exchangeType($v['type']);

                $exl[$kkk][] = array(
                    $v['uid'], $v['name'], $v['score'], $v['score_change'], $v['score_after'], $v['type'], $v['card_game_id'], $v['time']
                );
            }

            common::exportToExcel('余分流水报表' . $vvv . '.xls', $exl_title[$kkk], $exl[$kkk], $pageNum);
        }
        exit();
    }


    /**
     * @function 用户余分流水
     */
    public function userScoreLog()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $card_game_id  = $request->post('card_game_id');
        $pageNumber = intval($request->post('pageNumber'));
        $pageSize = intval($request->post('pageSize'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time) && empty($card_game_id) && empty($uid)) {
            $getTime = common::getWeek();
            $begin_time = strtotime($getTime[0]['date']);
            $end_time = strtotime($getTime[6]['date']);
        }
        $sql = UserScoreLog::alias('m')->join('user u', 'u.uid=m.uid')->where('m.user_ai',0)->where('m.tourist',0);             
           // ->where('m.tid', $tid)
        $sql->field('u.uid,u.name,u.username,m.id,m.score,m.score_change,m.score_after,m.time,m.note,m.type,m.card_game_id,m.tid');
        $sql ->order('m.time', 'desc');
        if (!empty($uid)) {
            $sql->where('m.uid', $uid);
        }
        if (!empty($card_game_id)) {
            $sql->where('m.card_game_id', $card_game_id);
        }
        if (!empty($begin_time)) {
            $sql->where('m.time', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('m.time', '<=', $end_time);
        }
        $logs = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        $data = [];
        $card_game_id_arr = [];
        foreach ($logs as $item) {
            $data[] = [
                'id' =>$item['id'],
                'time' => date('Y-m-d H:i:s', $item['time']),
                'note' => $item['note'],
                'name' => $item['name'],
                'username' => $item['username'],
                'uid' => $item['uid'],
                'score' => $item['score'],
                'score_change' => $item['score_change'],
                'score_after' => $item['score_after'],
                'type' => common::exchangeType($item['type']),
                'card_game_id' => $item['card_game_id']
            ];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'total' => $total
        ];
    }


    /**
     * @function 代理余分流水
     */
    public function agentScoreLog()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $agents_id = $request->post('agents_id');
        $pageNumber = intval($request->post('pageNumber'));
        $pageSize = intval($request->post('pageSize'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $sql = AgentScoreLog::alias('m')->join('agents a', 'm.agents_id=a.agents_id')
            ->where('m.agents_id', $agents_id)->where('m.tid',$tid)
            ->field('m.note,m.score,m.score_change,m.score_after,m.mktime,a.account as agents_account,a.name as agents_name')
            ->order('m.id', 'desc');
        if (!empty($begin_time)) {
            $sql->where('m.mktime', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('m.mktime', '<=', $end_time);
        }
        $logs = $sql->limit($start, $pageSize)->select();
        $data = [];
        $total = $sql->count();
        foreach ($logs as $item) {
            $data[] = [
                'time' => date('Y-m-d H:i:s', $item['mktime']),
                'note' => $item['note'],
                'agents_name' => $item['agents_name'],
                'agents_account' => $item['agents_account'],
                'score' => $item['score'],
                'score_change' => $item['score_change'],
                'score_after' => $item['score_after'],
            ];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => $data,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'total' => $total
        ];
    }
    
    /**
     * @function 删除用户流水记录
     */

    public function delScoreLog() {
        $request = Request::instance();
        $id = $request->get('id');
        UserScoreLog::where('id',$id)->where('tid',523)->delete();
        echo '流水记录id:'.$id.',删除成功';
    }
    
    /**
     * @function 删除用户流水记录
     */
    
    public function delBetLog() {
        $request = Request::instance();
        $id = $request->get('id');
        BetsMerge::where('id',$id)->where('tid',523)->delete();
        echo '下注记录id:'.$id.',删除成功';
    }

}