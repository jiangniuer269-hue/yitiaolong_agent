<?php

namespace app\index\controller;

use app\index\common;
use app\index\model\CardGame;
use app\index\model\SystemLog;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use think\facade\Request;
use WebSocket\Client;

class LanTian155
{
    const  DF_TID = 5;//对方tid
    const LT_TID = 584;//蓝天tid
    //http://agent9602.zmind.xyz  http://www.lantianws.com:90 http://test_agent9602.lwredyv.cn/
    const HTTPURL = 'http://154.23.221.76:9212';//蓝天后台地址
    const WSURL = 'ws://8.137.158.109:9612';//蓝天WS
    const LT_GROUPID = 155; //蓝天groupid
    const DF_GROUPID = 98; //对方groupid
    const TOKEN = '1771b4d732e6d6a05d1f0b1805627d507b5e';//蓝天登录token
    const USERNAME = 'zidong03'; //蓝天登录账号

    /**
     * @function 获取牌局状态
     * @return void
     */
    public function getCardGameStatus()
    {
        //   {"cmd":11,"ip":"112.114.106.103","ip_info":null,"time":null,"user_type":2,"token":"4167b4d732e6d6a05d1f0b1805627d507b5e","username":"shangshui168"}
        //开始投注
        //{"cmd":4210,"set":1,"qi":"","room_id":1,"boots_number":1,"ju":18,"xj":0,"msgtype":0,"counttime":30,"groupid":1}
        //停止投注
        // {"cmd":4210,"set":3,"groupid":1}
        //倒计时30秒
        //{"cmd":4210,"set":2,"second":30,"groupid":1}
        //牌局结算
        //{"cmd":3003,"set":7,"groupid":1,"room_id":1,"boots_number":1,"ju":22,"y":1,"zd":0,"xd":0,"xy":0,"lq":0,"fb":0,"super_he":0}
        //庄 1  闲 2 和 3 庄对 1 闲对 1 幸运六12倍 12 幸运六20倍 20
        //取消此局
        //{"cmd":4210,"set":5,"groupid":103}
        //靴号+1，清空路单
        // {"cmd":4210,"set":102,"boots_number":71,"ju":1,"groupid":109}
        //重新结算
        //{"cmd":4210,"set":100,"groupid":103,"gameID":420821,"y":2,"zd":0,"xd":0,"xy":"","lq":"0","fb":0,"super_he":"0"}
        //手动补路单
        //{"cmd":4210,"set":7,"groupid":159,"room_id":159,"y":2,"zd":0,"xd":0,"xy":0,"lq":0,"fb":0,"super_he":0}
        $cardGame = CardGame::where('tid', self::DF_TID)->where('groupid', self::DF_GROUPID)->field('id,room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,start,state,groupid,mktime,text')->order('id', 'desc')->limit(1)->find();
        if (!empty($cardGame)) {
            $cardGame103 = $cardGame->toArray();
            $curRoomId = self::LT_GROUPID;
            $boots_number_103 = $cardGame103['boots_number'];
            $ju_103 = $cardGame103['ju'];
            $start_103 = $cardGame103['start'];
            $game_id = $cardGame103['id'];
            $client = new Client(self::WSURL);
            $wsArr = self::loginWs();
            $date = '时间:' . date('Y-m-d H:i:s');
            $paiju = '牌局:' . $curRoomId . '-' . $boots_number_103 . '-' . $ju_103;
            //获取对方上一局的信息
            $last_ju = $ju_103 - 1;
            $cardGame_last = ['start' => 0];
            if ($last_ju > 0) {
                $cardGame_last = CardGame::where('tid', self::DF_TID)->where('groupid', self::DF_GROUPID)->where(['room_id' => self::DF_GROUPID, 'boots_number' => $boots_number_103, 'ju' => $last_ju])->field('room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,start,state,groupid,mktime,start')->order('id', 'desc')->limit(1)->find();
            }
            $curGameInfoTemp = file_get_contents(self::HTTPURL . '/v1/game155/info?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&game_id=' . $game_id);
            $curGameInfo = json_decode($curGameInfoTemp, true);
            $curGameState = -1;
            $curWsStatus = 0;//0 未开局 1 已发送已开局 2 已发送倒计时30s 3 已发送停止下注 4 已发送结算信息 5 取消此局 6 靴号+1，清空路单 7 手动补路单
            $mktime = 0;
            $curStart = 0;
            if ($curGameInfo['lastGame']['lastState'] < 2) {
                if ($curGameInfo['lastGame']['lastState'] == 1) {//上一局处于停止下注状态，先获取上局信息，结算
                    $zhuang = $cardGame_last['zhuang'];
                    $zhuang_dui = $cardGame_last['zhuang_dui'];
                    $xian_dui = $cardGame_last['xian_dui'];
                    $lucky_six = 0;
                    if ($cardGame_last['lucky_six'] == 6) {
                        $lucky_six = 12;
                    } elseif ($cardGame_last['lucky_six'] == 7) {
                        $lucky_six = 20;
                    }
                    $endArr = [
                        "cmd" => 3003,
                        "set" => 7,
                        "groupid" => $curRoomId,
                        "room_id" => $curRoomId,
                        "boots_number" => $boots_number_103,
                        "ju" => $last_ju,
                        "y" => $zhuang,
                        "zd" => $zhuang_dui,
                        "xd" => $xian_dui,
                        "xy" => $lucky_six,
                        "lq" => 0,
                        "fb" => 0,
                        "super_he" => 0
                    ];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($endArr));
                    // echo $client->receive();
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $last_ju . '&wsStatus=4&game_id=' . $game_id);
                    $logs = $date . '|' . $curRoomId . '-' . $boots_number_103 . '-' . $last_ju . '|牌局结算';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                } else {

                    $logs = $date . '|' . $curRoomId . '-' . $boots_number_103 . '-' . $last_ju . '|上局还未结算，本局已经开始，牌局状态不匹配，请重新开始同步';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                }
            }

            //靴号+1，清空路单
            if ($start_103 == 1 or $cardGame_last['start'] == 1) {
                if ($curStart == 0 && $curGameInfo['lastGame']['start'] == 0 && !empty($curGameInfo['data'])) {
                    $ludanArr = ["cmd" => 4210, "set" => 102, "boots_number" => $boots_number_103 + 1, "ju" => 1, "groupid" => $curRoomId];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($ludanArr));
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=6&game_id=' . $game_id);
                    $logs = $date . '|' . $paiju . '|靴号+1，清空路单';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                }
            }

            //执行手动补路单操作
            $textData = json_decode($cardGame103['text'], true);
            if (!empty($textData['cmd']) && !empty($textData['set']) && $textData['cmd'] == 4210 && $textData['set'] == 7 && empty($curGameInfo['data'])) {
                $zhuang = $cardGame103['zhuang'];
                $zhuang_dui = $cardGame103['zhuang_dui'];
                $xian_dui = $cardGame103['xian_dui'];
                $lucky_six = 0;
                if ($cardGame103['lucky_six'] == 6) {
                    $lucky_six = 12;
                } elseif ($cardGame103['lucky_six'] == 7) {
                    $lucky_six = 20;
                }
                $buLudanArr = [
                    "cmd" => 4210,
                    "set" => 7,
                    "groupid" => $curRoomId,
                    "room_id" => $curRoomId,
                    "y" => $zhuang,
                    "zd" => $zhuang_dui,
                    "xd" => $xian_dui,
                    "xy" => $lucky_six,
                    "lq" => 0,
                    "fb" => 0,
                    "super_he" => 0
                ];
                $client->text(json_encode($wsArr));
                $client->text(json_encode($buLudanArr));
                // echo $client->receive();
                $client->close();
                //修改当局ws状态
                file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=7&game_id=' . $game_id);
                $logs = $date . '|' . $curRoomId . '-' . $boots_number_103 . '-' . $ju_103 . '|手动补路单';
                echo $logs;
                common::write_log($logs);
                exit();
            }

            if (!empty($curGameInfo['data'])) {
                $curGameState = $curGameInfo['data']['state'];
                $curWsStatus = $curGameInfo['data']['wsstatus'];
                $mktime = $curGameInfo['data']['mktime'];
                $curStart = $curGameInfo['data']['start'];
            } elseif (empty($curGameInfo['data']) && $cardGame103['state'] > 0) {
                $logs = $date . '|' . $paiju . '|对方处理停止下注或已结算状态，牌局状态不匹配，请从下局开始同步';
                echo $logs;
                common::write_log($logs);
                exit();
            }


            //开局10秒后发送倒计时30s
            if ($curGameState == 0 && time() - $mktime > 20 && $curWsStatus == 1) {
                $countArr = ["cmd" => 4210, "set" => 2, "second" => 30, "groupid" => $curRoomId];
                $client->text(json_encode($wsArr));
                $client->text(json_encode($countArr));
                // echo $client->receive();
                $client->close();
                //修改当局ws状态
                file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=2&game_id=' . $game_id);
                $logs = $date . '|' . $paiju . '|倒计时30s';
                echo $logs;
                common::write_log($logs);
                exit();
            }

            if ($cardGame103['state'] > $curGameState) {
                if ($cardGame103['state'] == 0) {//开始投注
                    $beginArr = [
                        "cmd" => 4210,
                        "set" => 1,
                        "qi" => "",
                        "room_id" => $curRoomId,
                        "boots_number" => $boots_number_103,
                        "ju" => $ju_103,
                        "xj" => 0,
                        "msgtype" => 0,
                        "counttime" => 30,
                        "groupid" => $curRoomId
                    ];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($beginArr));
                    // echo $client->receive();
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=1&game_id=' . $game_id);
                    $logs = $date . '|' . $paiju . '|开始下注';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                } elseif ($cardGame103['state'] == 1) {//停止下注
                    $stopArr = [
                        "cmd" => 4210,
                        "set" => 3,
                        "groupid" => $curRoomId
                    ];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($stopArr));
                    // echo $client->receive();
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=3&game_id=' . $game_id);
                    $logs = $date . '|' . $paiju . '|停止下注';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                } elseif ($cardGame103['state'] == 2) {//牌局结算
                    $zhuang = $cardGame103['zhuang'];
                    $zhuang_dui = $cardGame103['zhuang_dui'];
                    $xian_dui = $cardGame103['xian_dui'];
                    $lucky_six = 0;
                    if ($cardGame103['lucky_six'] == 6) {
                        $lucky_six = 12;
                    } elseif ($cardGame103['lucky_six'] == 7) {
                        $lucky_six = 20;
                    }
                    $endArr = [
                        "cmd" => 3003,
                        "set" => 7,
                        "groupid" => $curRoomId,
                        "room_id" => $curRoomId,
                        "boots_number" => $boots_number_103,
                        "ju" => $ju_103,
                        "y" => $zhuang,
                        "zd" => $zhuang_dui,
                        "xd" => $xian_dui,
                        "xy" => $lucky_six,
                        "lq" => 0,
                        "fb" => 0,
                        "super_he" => 0
                    ];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($endArr));
                    // echo $client->receive();
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=4&game_id=' . $game_id);
                    $logs = $date . '|' . $paiju . '|牌局结算';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                } elseif ($cardGame103['state'] == 3) {//取消此局
                    $cancelArr = ["cmd" => 4210, "set" => 5, "groupid" => $curRoomId];
                    $client->text(json_encode($wsArr));
                    $client->text(json_encode($cancelArr));
                    // echo $client->receive();
                    $client->close();
                    //修改当局ws状态
                    file_get_contents(self::HTTPURL . '/v1/game155/wsstatus?room_id=' . $curRoomId . '&boots_number=' . $boots_number_103 . '&ju=' . $ju_103 . '&wsStatus=5&game_id=' . $game_id);
                    $logs = $date . '|' . $paiju . '|取消此局';
                    echo $logs;
                    common::write_log($logs);
                    exit();
                }
            }
        }
        // $cardGame109 = CardGame::where('tid', 262)->where('groupid',109)->field('room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,start,state,groupid')->order('id','desc')->limit(1)->find()->toArray();
    }

    /**
     * @function 获取牌局信息
     */
    public function getGameInfo()
    {
        $request = Request::instance();
        $room_id = $request->get('room_id');
        $boots_number = $request->get('boots_number');
        $ju = $request->get('ju');
        $game_id = $request->get('game_id');
        $startGame = CardGame::where('start', 1)->where('room_id', $room_id)->field('id')->order('id', 'desc')->limit(1)->find();
        $startid = 0;
        if (!empty($startGame)) {
            $startid = $startGame['id'];
        }
        $info = CardGame::where(['room_id' => $room_id, 'boots_number' => $boots_number, 'ju' => $ju, 'game_id' => $game_id])->where('id', '>', $startid)->field('state,wsstatus,mktime,start')->order('id', 'desc')->limit(1)->find();
        //上一局的牌局信息
        $lastGameInfo = ['lastState' => 2, 'start' => 0];
        if ($ju > 1) {
            $lastInfo = CardGame::where(['room_id' => $room_id, 'boots_number' => $boots_number, 'ju' => $ju - 1])->where('id', '>', $startid)->field('state,start')->order('id', 'desc')->limit(1)->find();
            $lastGameInfo['lastState'] = $lastInfo['state'];
            $lastGameInfo['start'] = $lastInfo['start'];
        }
//        if (!empty($info)) {
//            return ['code' => 200, 'msg' => '房间信息存在', 'data' => $info];
//        } else {
//            return ['code' => 500, 'msg' => '房间信息不存在'];
//        }
        return ['code' => 200, 'msg' => '获取房间信息', 'data' => $info, 'lastGame' => $lastGameInfo];
    }

    /**
     * @function 修改ws状态
     */
    public function updateWsStatus()
    {
        $request = Request::instance();
        $room_id = $request->get('room_id');
        $boots_number = $request->get('boots_number');
        $ju = $request->get('ju');
        $wsStatus = $request->get('wsStatus');
        $game_id = $request->get('game_id');
        CardGame::where(['room_id' => $room_id, 'boots_number' => $boots_number, 'ju' => $ju])->where('mktime', '>', time() - 3600)->update(['wsstatus' => $wsStatus, 'game_id' => $game_id]);
        return 1;
    }

    /**
     * @function 重新结算
     * @return void
     */
    public function countAgain()
    {
        $date = date('Y-m-d H:i:s') . '|';
        $syslog = SystemLog::where('type', 22)->where('again_status', 0)->field('id')->limit(1)->order('id', 'desc')->find();
        if (!empty($syslog)) {
            $userlog = UserScoreLog::where('tid', self::DF_TID)->where('type', 121)->field('card_game_id')->limit(1)->order('id', 'desc')->find();
            $card_game_id = $userlog['card_game_id'];
            $cardGame = CardGame::where('tid', self::DF_TID)->where('id', $card_game_id)->field('room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,start,state,groupid,mktime,text')->order('id', 'desc')->limit(1)->find();
            $room_id = $cardGame['room_id'];
            $boots_number = $cardGame['boots_number'];
            $ju = $cardGame['ju'];
            $pai_ju = $room_id . '-' . $boots_number . '-' . $ju . '|';
            //获取重新结算状态
            $gameAgainRes = file_get_contents(self::HTTPURL . '/v1/game155/gameagain?boots_number=' . $boots_number . '&ju=' . $ju);
            $gameAgainTemp = json_decode($gameAgainRes, TRUE);
            $gameAgainArr = $gameAgainTemp['data'];
            if ($gameAgainArr['again_status'] == 0) {
                $game_result01 = intval($cardGame['zhuang'] . $cardGame['zhuang_dui'] . $cardGame['xian_dui'] . $cardGame['lucky_six']);
                $game_result02 = intval($gameAgainArr['zhuang'] . $gameAgainArr['zhuang_dui'] . $gameAgainArr['xian_dui'] . $gameAgainArr['lucky_six']);
                $logs = $date . $pai_ju . '进行重新结算，当前结果：' . $game_result02 . ', 重新结算结果：' . $game_result01;
                echo $logs;
                common::write_log($logs);
                if (abs($game_result01 - $game_result02) > 0) {
                    //{"cmd":4210,"set":100,"groupid":103,"gameID":420821,"y":2,"zd":0,"xd":0,"xy":"","lq":"0","fb":0,"super_he":"0"}
                    $recountArr = [
                        "cmd" => 4210,
                        "set" => 100,
                        "groupid" => self::LT_GROUPID,
                        "gameID" => $gameAgainArr['id'],
                        "y" => $cardGame['zhuang'],
                        "zd" => $cardGame['zhuang_dui'],
                        "xd" => $cardGame['xian_dui'],
                        "xy" => $cardGame['lucky_six'],
                        "lq" => "0",
                        "fb" => 0,
                        "super_he" => 0
                    ];
                    $client = new Client(self::WSURL);
                    $client->text(json_encode(self::loginWs()));
                    $client->text(json_encode($recountArr));
                    // echo $client->receive();
                    $client->close();
                    //修改重新结算状态
                    file_get_contents(self::HTTPURL . '/v1/game155/gameagainstatus?card_game_id=' . $gameAgainArr['id']);
                } else {
                    $logs = $date . $pai_ju . '结果一致，无需操作';
                    echo $logs;
                    common::write_log($logs);
                }
            } elseif ($gameAgainArr['again_status'] == 1) {
                $logs = $date . $pai_ju . '已进行重新结算，请勿重复操作';
                echo $logs;
                common::write_log($logs);
            }
            SystemLog::where('id', $syslog['id'])->update(['again_status' => 1]);
            return 1;
        }
    }

    /**
     * @function  修改房间信息
     * @return void
     */
    public function updateRoomInfo()
    {
        $request = Request::instance();
        $groupid = $request->post('groupid');
        $mark = $request->post('mark');
        $video_link = $request->post('video_link');
        $groupname = $request->post('groupname');
        TeamRoom::where('tid', self::LT_TID)->where('groupid', $groupid)->update(['mark' => $mark, 'video_link' => $video_link, 'groupname' => $groupname]);
        return 1;
    }

    /**
     * @function 查询牌局重新结算状态
     */
    public function gameAgain()
    {
        $request = Request::instance();
        $boots_number = $request->get('boots_number');
        $ju = $request->get('ju');
        $game = CardGame::where('boots_number', $boots_number)->where('ju', $ju)->where('groupid', self::LT_GROUPID)->where('state', 2)->where('mktime', '>', time() - 3600)->field('id,room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,start,state,groupid,mktime,again_status')->order('id', 'desc')->limit(1)->find();
        if (empty($game)) {
            $game = ['again_status' => 1];
        }
        return ['code' => 200, 'msg' => '重新结算', 'data' => $game];
    }

    /**
     * @function  登录ws
     * @return array
     */
    public static function loginWs()
    {
        $wsArr = [
            "cmd" => 11,
            "ip" => "112.114.106.103",
            "ip_info" => null,
            "time" => null,
            "user_type" => 2,
            "token" => self::TOKEN,
            "username" => self::USERNAME,
        ];
        return $wsArr;
    }

    /**
     * @function 修改重新结算状态
     */
    public function updateAgainStatus()
    {
        $request = Request::instance();
        $card_game_id = $request->get('card_game_id');
        CardGame::where('id', $card_game_id)->update(['again_status' => 1]);
        return 1;
    }

    /**
     * @function  自动结算开关
     * @return void
     */
    public function docmd()
    {
        $request = Request::instance();
        $cmdtype = intval($request->post('cmdtype'));
        $doIt = FALSE;
        if ($cmdtype == 1) {//开启同步
            $doIt = TRUE;
            $msg = '自动开奖已开启';
            $cmd = "cd /home/www/wwwroot/php-task-master/ && nohup php run.php start --log=false > /dev/null &";//命令
        } elseif ($cmdtype == 2) {
            $doIt = TRUE;
            $msg = '自动开奖已停止';
            $cmd = "cd /home/www/wwwroot/php-task-master/ &&  php run.php stop";//命令
        }
        if ($doIt) {
            $user = "root";//远程用户名
            $pass = "EA2r22n!@168168";//远程密码
            $connection = ssh2_connect('116.63.133.208', 1998);
            ssh2_auth_password($connection, $user, $pass);
            $ret = ssh2_exec($connection, $cmd);
            stream_set_blocking($ret, true);
            return ['code' => 200, 'msg' => $msg];
        } else {
            return ['code' => 500, 'msg' => '参数错误，操作失败'];
        }
    }

    /**
     * @function 获取自动开奖房间
     * @return void
     */
    public function getCmdRoom()
    {
        $room = TeamRoom::where('tid', 584)->whereIn('id', [156, 97])->field('groupname,id')->select();
        return ['code' => 200, 'msg' => '自动开奖房间', 'data' => $room];
    }

    /**
     * @function  修改机器人头像
     * @return void
     */
    public function updateAiImg()
    {
        $head = file_get_contents('http://agent9605.fmfcys.org/v1/game155/getAiImg');
        $headArrTemp = json_decode($head, TRUE);
        $headArr = $headArrTemp['data'];
        $user = User::where('tid', 584)->where('ai', 1)->field('uid')->select();
        foreach ($user as $u) {
            $number = array_rand($headArr);
            User::where('uid', $u['uid'])->update(['head' => $headArr[$number]['head']]);
        }
        echo '机器人头像修改执行完毕';
        exit();
    }

    /**
     * @function 获取机器人头像
     */
    public function getAiImg()
    {
        $user = User::where('ai', 1)->where('status', 0)->where('tid', 5)->field('head')->select();
        return ['code' => 200, 'msg' => '机器人头像', 'data' => $user];
    }

    /**
     * @function  是否需要清空路单
     * @return void
     */
    public function clean()
    {
        $request = Request::instance();
        $room_id = $request->get('room_id');
        $game = CardGame::where('room_id', $room_id)->order('id', 'desc')->field('boots_number')->limit(1)->find();
        $game01 = CardGame::where('room_id', $room_id)->where('boots_number', $game['boots_number'])->where('start', 1)->where('mktime', '>', time() - 3600)->field('boots_number')->find();
        if (!empty($game01)) {
            return ['code' => 200, 'msg' => '路单已清空', 'data' => ['boots_number' => $game['boots_number']]];
        } else {
            return ['code' => 500, 'msg' => '上靴路单未清空', 'data' => ['boots_number' => $game['boots_number']]];
        }
    }


}
