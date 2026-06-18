<?php

namespace app\index\controller;

use app\index\common;
use app\index\model\BetsLog;
use app\index\model\CardGame;
use app\index\model\SystemLog;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use think\facade\Request;
use WebSocket\Client;

class XianHongManage
{
    /**
     * @function 获取下注总额
     */
    public function getBetsTotal()
    {
        $request = Request::instance();
        $groupid = $request->get('groupid');
        $tid = $request->get('tid');
        $startGame = CardGame::where('start', 1)->where('groupid', $groupid)->where('tid', $tid)->field('id')->order('id', 'desc')->limit(1)->find();
        $startid = 0;
        if (!empty($startGame)) {
            $startid = $startGame['id'];
        }
        $game = CardGame::where('state', 0)->where('groupid', $groupid)->where('tid', $tid)->where('id', '>', $startid)->field('id')->order('id', 'desc')->limit(1)->find();
        if (!empty($game)) {
            //获取单边限红
            $teamroom = TeamRoom::where('groupid', $groupid)->where('tid', $tid)->field('single')->find();
            $single = $teamroom['single'];
            $card_game_id = $game['id'];
            $betsTotal = BetsLog::where('type', 2)->where('card_game_id', $card_game_id)->where('user_ai', 0)->where('state', 0)->sum('odds');
            if ($betsTotal > $single) {//超过单边限红
                $betslog = BetsLog::where('type', 2)->where('card_game_id', $card_game_id)->where('user_ai', 0)->where('state', 0)->order('id', 'desc')->field('id,uid,odds,text')->find();
                if (!empty($betslog)) {
                    $uid = $betslog['uid'];
                    $odds = $betslog['odds'];
                    $id = $betslog['id'];
                    $text = $betslog['text'];
                   $updateres= BetsLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('state', 0)->update(['state' => 3]);
                   print_r($updateres);exit;
                }
            } else {
                $logs = date('Y-m-d H:i:s') . '|牌局ID' . $card_game_id . ':未超过单边限红';
                echo $logs;
                common::write_log($logs, 'xianhong');
                exit();
            }
        } else {
            $logs = date('Y-m-d H:i:s') . '|暂无牌局记录';
            echo $logs;
            common::write_log($logs, 'xianhong');
            exit();
        }
    }
}
