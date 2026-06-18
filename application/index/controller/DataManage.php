<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/12/13
 * Time: 7:47 PM
 */

namespace app\index\controller;

use app\index\model\BetsLog;
use app\index\model\CardGame;
use app\index\model\CardGameReport;
use app\index\model\BetsMerge;
use app\index\model\ChatPacket;
use app\index\model\IntegralDate;
use app\index\model\IntegralLog;
use app\index\model\LosewinDay;
use app\index\model\NnCardGameReport;
use app\index\model\TeamConfig;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use think\facade\Request;
use app\index\model\LhCardGameReport;
use app\index\model\ZjhCardGameReport;
use app\index\common;
use app\index\model\CardGameReportAdd;
use app\index\model\CheckLog;

class DataManage
{

    /**
     * @function 数据面板
     */
    public function data_panel()
    {
        //有效下注总人数

    }

    /**
     * @function 检查客户盈亏
     */
    public function checkUserWin()
    {
        $request = Request::instance();
        $begin_time = strtotime($request->post('begin_time'));
        $end_time = strtotime($request->post('end_time'));
    
        $sql = CardGameReport::order('id', 'desc');        
        $sql->where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time);
        $sql->field('card_game_id,khyk,zxyk_zc');
        $data = $sql->select();
        foreach ($data as $item) {
            $card_game_id = $item['card_game_id'];
            $khyk = $item['khyk'];
            $zxyk_zc = $item['zxyk_zc'];
            $khyk_all = $khyk - $zxyk_zc;
            $user_score = UserScoreLog::where('user_ai', 0)->where('tourist', 0)
                ->where('card_game_id', $card_game_id)->whereIn('type', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 111,112, 121, 110])->sum('score_change');
               $wcha_data =  $khyk_all- $user_score ;
               if ($wcha_data>2) {
                echo '数据有误差,card_game_id:' . $card_game_id . '|' . PHP_EOL;
                print_r($user_score . '|' . $khyk_all . '|'. $card_game_id . '|' . PHP_EOL);
              }else {
                  
                 // print_r($user_score . '|' . $khyk_all . '|数据正常'.intval($wcha_data).'|'. $card_game_id . '|' . PHP_EOL);
              }
        }
        echo '执行完毕';
    }

    /**
     * @function 检查数据用户输赢数
     */
    public function checkdata()
    {
        set_time_limit(0);
        $request = Request::instance();
        $begin_time = strtotime($request->post('begin_time'));
        $end_time = strtotime($request->post('end_time'));

        $tid = $request->post('tid');
            $sql = CardGame::order('id', 'desc');
      
        $sql->where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time);
        $sql->where('tid', $tid);
        $sql->field('id');
        $data = $sql->select();
   
        foreach ($data as $item) {
            $khyk_all = 0;
            $win_all = 0;
            $card_game_id = $item['id'];
            $sql = CardGameReport::where('card_game_id', $card_game_id);         
            $sql->field('card_game_id,khyk,zxyk_zc');
            $data_report = $sql->find();          
            $khyk = $data_report['khyk'];
            $zxyk_zc = $data_report['zxyk_zc'];
            echo 'khyk'.$khyk.'__'.'zxyk_zc'.'__'.$zxyk_zc. PHP_EOL;
            $khyk_all = ($khyk-$zxyk_zc);
  
            $win = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
            // echo $game_type . ' card_game_id:' . $card_game_id . ' khyk:' . $khyk . ' win:' . $win . PHP_EOL;
            $win_all += $win;
            if (abs($khyk_all - $win) > 2) {
                echo '数据异常' . PHP_EOL;
                echo BetsMerge::getLastSql(). PHP_EOL;
                echo 'khyk_all:' . $khyk_all . ' win:' . $win . PHP_EOL;
                echo $card_game_id . '-' . ($khyk_all - $win) . '||' . PHP_EOL;
            }
            echo 'khyk:' . $khyk_all . ' win:' . $win . PHP_EOL;
            echo $card_game_id . '-' . ($khyk_all - $win) . '||' . PHP_EOL;
        }
        echo '执行成功';

        // echo 'khyk_all:' . $khyk_all . '  win_all:' . $win_all;
    }

    /**
     * @function 检查积分
     */
    public function checkInte()
    {
        $request = Request::instance();
        $begin_time = strtotime($request->post('begin_time'));
        $end_time = strtotime($request->post('end_time'));
        $uid = $request->post('uid');
        $betsData = BetsMerge::field('id,uid,user_zx_xm,user_sb_xm,user_lucky_xm,card_game_id')
            ->where('uid', $uid)->where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time)->select();
        $inteDate = IntegralLog::field('score,uid,card_game_id')
            ->where('uid', $uid)->where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time)->select();
        $betsArray = [];
        $inteArray = [];
        foreach ($betsData as $item) {
            $uid = $item['uid'];
            $card_game_id = $item['card_game_id'];
            $betsArray[$uid . '_' . $card_game_id] = $item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'];
        }
        foreach ($inteDate as $value) {
            $uid = $value['uid'];
            $card_game_id = $value['card_game_id'];
            $inteArray[$uid . '_' . $card_game_id] = $value['score'];
        }
        $betsxm = 0;
        $intescore = 0;
        foreach ($betsArray as $k => $v) {
            $betsxm += $v;
            $intescore += $inteArray[$k];
            // echo 'bets_' . $v . '|' . 'inte_'.$inteArray[$k] . PHP_EOL;
            if ($v != $inteArray[$k]) {
                echo $k . '__' . PHP_EOL;
            }
        }
        echo 'bets_' . $betsxm . '|' . 'inte_' . $intescore . PHP_EOL;
        echo '执行完毕';
    }

    /**
     * @function 检查输赢表
     */
    public function checkLosewin()
    {
        $request = Request::instance();
        $begin_time = date('Ymd', strtotime($request->post('begin_time')));
        $end_time = date('Ymd', strtotime($request->post('end_time')));
        $data = LosewinDay::where('datetime', '>=', $begin_time)->where('datetime', '<=', $end_time)->select();
        foreach ($data as $item) {
            $uid = $item['uid'];
            $datetime = $item['datetime'];
            $inte = IntegralDate::where('ukey', $uid . '_' . $datetime)->find();
            if ($inte['integral'] != $item['integral_make']) {
                echo '用户ID :' . $uid . '_' . ' 日期：' . $datetime;
            }
        }
        echo '执行完毕';
    }

    /**
     * @function 检查流水明细
     */
    public function checkUserScoreLog()
    {
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $sql = UserScoreLog::field('uid,score,score_after,id');
        $sql->where('user_ai', 0)->where('tourist', 0);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('time', '<=', $end_time_sql);
        }
        $dataUSer = $sql->group('uid')->select();
        foreach ($dataUSer as $item) {
            $uid = $item['uid'];
            $sql = UserScoreLog::field('uid,score,score_after,id');
            $sql->where('user_ai', 0)->where('tourist', 0);
            if (!empty($begin_time)) {
                $begin_time_sql = strtotime($begin_time);
                $sql->where('time', '>=', $begin_time_sql);
            }
            if (!empty($end_time)) {
                $end_time_sql = strtotime($end_time);
                $sql->where('time', '<=', $end_time_sql);
            }
            $data = $sql->where('uid', $uid)->select();
            $score_after = 0;
            foreach ($data as $value) {
                if ($score_after == 0) {
                    $score_after = $value['score_after'];
                } else {
                    if ($score_after != $value['score']) {
                        echo 'id:' . $value['id'] . ' uid:' . $value['uid'];
                        exit;
                    }
                    $score_after = $value['score_after'];
                }
            }

            echo '会员uid:' . $uid . '_没有问题' . PHP_EOL;
        }
        echo '执行完毕';
    }


    /**
     * @function 检查客人余分
     */
    public function checkScore()
    {
        $users = User::where('score', '<', 0)->field('uid')->select();
        if (!empty($users)) {
            foreach ($users as $item) {
                $uid = $item['uid'];
                //删除错误数据
                UserScoreLog::where('uid', $uid)->where('type', 121)->where('score_after', '<', 0)->delete();
                $scoreData = UserScoreLog::where('uid', $uid)->order('id', 'desc')->limit(1)->find();
                $score_after = 0;
                if (!empty($scoreData)) {
                    $score_after = $scoreData['score_after'];
                }
                User::where('uid', $uid)->update(['score' => $score_after, 'csf' => $score_after]);
                echo '用户：' . $uid . ' 执行完毕';
            }
        } else {
            echo '余分正常';
        }
    }


    /**
     * @functiion 加注
     */
    public function addBets()
    {
        $request = Request::instance();
        $bets_log_id = $request->post('bets_log_id');
        $odds = $request->post('odds');
        $betslog = BetsLog::where('id', $bets_log_id)->find();
        if (!empty($betslog)) {
            $bets_log_win = $betslog['win'];
            if ($bets_log_win <= 0) {
                echo '这局输了啊';
                exit();
            }
            $agents_share_rate = 0;
            $uid = $betslog['uid'];
            $tid = $betslog['tid'];
            //获取会员信息
            $user = User::where('uid', $uid)->field('agents_share_rate')->find();
            if (!empty($user)) {
                $agents_share_rate = $user['agents_share_rate'];
            }
            $card_game_id = $betslog['card_game_id'];
            $betslog_rate = BetsLog::where('card_game_id', $card_game_id)->where('agents_share_rate', '>', 0)->where('user_ai', 0)->select();
            if (count($betslog_rate) > 0) {
                echo '存在占成下注';
                exit();
            }
            $groupid = $betslog['groupid'];
            $type = $betslog['type'];
            $score_before = $betslog['score_before'];
            $odds_old = $betslog['odds'];
            if ($odds - $odds_old <= 0) {
                echo '重复执行';
                exit;
            }
            $zhuang = 0;
            $xian = 0;
            $zhuang_yx = 0;
            $xian_yx = 0;
            $zxyk = 0;
            $zxyk_zc = 0;
            $game = CardGame::where('id', $card_game_id)->field('zhuang,boots_number,ju')->find();
            if ($game['zhuang'] == 3) {
                echo '开的是和啊';
                exit;
            }
            if ($type == 1) {
                $text = '庄' . $odds;
                $rate = 0.95;
                $win = $odds * $rate;
                $score_after = $score_before + $win;
                $zhuang = $odds;
            } else {
                $text = '闲' . $odds;
                $rate = 1;
                $win = $odds;
                $score_after = $score_before + $win;
                $xian = $odds;
            }
            $updateData = [
                'text' => $text,
                'odds' => $odds,
                'xm' => $odds,
                'win' => $win,
                'score_after' => $score_after
            ];
            BetsLog::where('id', $bets_log_id)->update($updateData);
            //修改投注记录
            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid;
            $mData = BetsMerge::where('ukey', $bets_ukey)->find();
            if (!empty($mData)) {
                $mUpdateData = [
                    'zhuang' => $zhuang,
                    'xian' => $xian,
                    'win' => $win,
                    'score_after' => $score_after,
                    'user_zx_xm' => $odds,
                    'user_zx_losewin' => $win,
                    'zhuang_yx' => $zhuang_yx,
                    'xian_yx' => $xian_yx,
                    'user_zx_losewin_yx' => $win
                ];
                BetsMerge::where('ukey', $bets_ukey)->update($mUpdateData);
            }
            //修改牌局报表
            $report = CardGameReport::where('card_game_id', $card_game_id)->find();
            if (!empty($report)) {
                $config = TeamRoom::where('groupid', $groupid)->field('mantissa,odds_zx_min,mark')->find();
                $mantissa = intval($config['mantissa']);
                $zhuang_total = $report['zhuang_total'];
                $xian_total = $report['xian_total'];
                $zhuang_zc_total = $report['zhuang_zc_total'];
                $xian_zc_total = $report['xian_zc_total'];

                $dc_total = 0;
                if ($type == 1) {
                    $zhuang_total = $zhuang_total + ($odds - $odds_old);
                    $zhuang_zc_total += ($odds - $odds_old) * $agents_share_rate / 10;
                } else {
                    $xian_total = $xian_total + ($odds - $odds_old);
                    $xian_zc_total += ($odds - $odds_old) * $agents_share_rate / 10;
                }

                if ($zhuang_total > $xian_total) {
                    $dc_total = $xian_total * (1 - $agents_share_rate / 10);
                } elseif ($zhuang_total < $xian_total) {
                    $dc_total = $zhuang_total * (1 - $agents_share_rate / 10);
                }
                $tm = 0;
                $ws = 0;
                $dcyk = 0;
                $tmyk = 0;
                $wsyk = 0;
                $zxxm = 0;
                $sbyk = $report['sbyk'];
                $luckysix_yk = $report['luckysix_yk'];
                if ($zhuang_total != 0 || $xian_total != 0) {
                    if ($mantissa > 0) {
                        $tm = floor(abs($zhuang_total - $xian_total - ($zhuang_total - $xian_total) * $agents_share_rate / 10) / $mantissa) * $mantissa;
                    } elseif ($mantissa == 0) {
                        $tm = abs($zhuang_total - $xian_total - ($zhuang_total - $xian_total) * $agents_share_rate / 10);
                    }
                    if ($tm < $config['odds_zx_min']) {
                        $tm = 0;
                    }
                    $ws = abs($zhuang_total - $xian_total) * (1 - $agents_share_rate / 10) - $tm;
                }
                if ($game['zhuang'] == 1) {
                    $dcyk = $dc_total - ($dc_total * 0.95);
                }
                if ($zhuang_total > $xian_total) {
                    if ($game['zhuang'] == 1) {
                        $wsyk = -($ws * 0.95);
                        $tmyk = -($tm * 0.95);
                        $zxyk = $xian_total - $zhuang_total * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $zhuang_total - $xian_total;
                    }
                } elseif ($zhuang_total < $xian_total) {
                    if ($game['zhuang'] == 1) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $xian_total - $zhuang_total * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $wsyk = -$ws;
                        $tmyk = -$tm;
                        $zxyk = $zhuang_total - $xian_total;
                    }
                }
                $zxyk_zc = $zxyk * $agents_share_rate / 10;
                //统计代理总赢
                $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
                //客户盈亏
                $khyk = -($wsyk + $sbyk + $dcyk + $tmyk + $luckysix_yk);
                //台面筹码
                $tmcm = $dlzy + $khyk;

                $rUpdata = [
                    'boots_number'=>$game['boots_number'],
                    'ju'=>$game['ju'],
                    'zhuang_total' => $zhuang_total,
                    'xian_total' => $xian_total,
                    'dc_total' => $dc_total,
                    'ws' => $ws,
                    'tm' => $tm,
                    'dcyk' => $dcyk,
                    'tmyk' => $tmyk,
                    'wsyk' => $wsyk,
                    'zxxm' => $zxxm,
                    'sbyk' => $sbyk,
                    'luckysix_yk' => $luckysix_yk,
                    'dlzy' => $dlzy,
                    'khyk' => $khyk,
                    'tmcm' => $tmcm,
                    'zxyk' => $zxyk,
                    'zxyk_zc' => $zxyk_zc,
                    'zhuang_zc_total' => $zhuang_zc_total,
                    'xian_zc_total' => $xian_zc_total,
                    'card_game_id'=>$card_game_id
                ];
                CardGameReportAdd::create($rUpdata);
                
            }
            //修改历史消息
            ChatPacket::where('fromuid', $uid)->where('card_game_id', $card_game_id)->update(['message' => $text]);
            //修改投注表
            $chatPacket = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->field('message')->find();
            $chatPacketArr = json_decode($chatPacket['message'], true);
            $odds_total = 0;
            foreach ($chatPacketArr[0] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item[$type] = $odds;
                    if ($type == 1) {
                        $item['z'] = $odds;
                    } elseif ($type == 2) {
                        $item['x'] = $odds;
                    }
                    $item['win'] = -$odds;
                    $odds_total += $odds;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $odds_total += $item[$type];
                }
                if ($item['key'] == -1) {
                    $item[$type] = $odds_total;
                    if ($type == 1) {
                        $item['z'] = $odds_total;
                    } elseif ($type == 2) {
                        $item['x'] = $odds_total;
                    }
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->update(['message' => json_encode($chatPacketArr)]);
            //修改余分表
            $chatPacketyf = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->field('message')->find();
            $chatPacketArryf = json_decode($chatPacketyf['message'], true);
            $yf_total = 0;
            $win_total = 0;
            $yf_after = 0;
            if ($game['zhuang'] == 1) {
                $yf_after = $score_before + $win;
            } elseif ($game['zhuang'] == 2) {
                $yf_after = $score_before + $win;
            }
            $yf_after = floor($yf_after);
            foreach ($chatPacketArryf['data'] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item['score'] = $yf_after;
                    $item['win'] = $win;
                    $yf_total += $yf_after;
                    $win_total += $win;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $yf_total += $item['score'];
                    $win_total += $item['win'];
                }
                if ($item['key'] == -1) {
                    $item['score'] = $yf_total;
                    $item['win'] = $win_total;
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->update(['message' => json_encode($chatPacketArryf)]);
            //修改余分流水表
            $usLog = UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->select();
            $score_after_us1 = 0;
            $us_chanage = 0;
            foreach ($usLog as $v1) {
                if ($v1['type'] == $type) {
                    $score_us = $v1['score'];
                    $score_change_us = -$odds;
                    $score_after_us = $score_us - $odds;
                    $score_after_us1 = $score_after_us;
                    $note = $config['mark'] . '桌-' . $game['boots_number'] . '靴-' . $game['ju'] . '局 ' . $text;
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', $type)
                        ->update(['score' => $score_us, 'score_change' => $score_change_us, 'score_after' => $score_after_us, 'note' => $note]);
                }
                if ($v1['type'] == 111) {
                    if ($game['zhuang'] == 1) {
                        $us_chanage = $odds + $odds * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $us_chanage = $odds + $odds * 1;
                    }
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', 111)
                        ->update(['score' => $score_after_us1, 'score_change' => $us_chanage, 'score_after' => $yf_after]);
                }
            }
            //修改user余分
            User::where('uid', $uid)->update(['score' => $yf_after]);
            //修改积分记录
            $tconfig = TeamConfig::where('tid', $tid)->find();
            $score_rate = $tconfig['score_rate'];
            $integral = 0;
            if ($score_rate > 0) {
                $integral = common::math_div($odds, $score_rate, 2);
            }
            $ilog = IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)->field('integral')->find();
            $cinte = $integral - $ilog['integral'];
            IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)
                ->update(['score' => $odds, 'integral' => $integral]);
            $datetime = date('Ymd', time());
            $intedate = IntegralDate::where('uid', $uid)->where('date_time', $datetime)->find();
            $dateinte = $intedate['integral'] + $cinte;
            $integral_total = $intedate['integral_total'] + $cinte;
            IntegralDate::where('uid', $uid)->where('date_time', $datetime)->update(['integral' => $dateinte, 'integral_total' => $integral_total]);
            echo '修改完成';
        } else {
            echo '无下注记录';
        }
    }
    
    
    /**
     * @function 处理自营报表
     */
    public function doZyReport() {
        $data = CardGameReportAdd::where('tongji_report',0)
        ->field('boots_number,ju,zhuang_total,xian_total,dc_total,ws,tm,dcyk,tmyk,wsyk,zxxm,sbyk,luckysix_yk,dlzy,khyk,tmcm,zxyk,zxyk_zc,zhuang_zc_total,xian_zc_total,card_game_id')->find();
        if (!empty($data)){
                $rUpdata = [
                'zhuang_total' => $data['zhuang_total'],
                'xian_total' => $data['xian_total'],
                'dc_total' => $data['dc_total'],
                'ws' => $data['ws'],
                'tm' => $data['tm'],
                'dcyk' => $data['dcyk'],
                'tmyk' => $data['tmyk'],
                'wsyk' => $data['wsyk'],
                'zxxm' => $data['zxxm'],
                'sbyk' => $data['sbyk'],
                'luckysix_yk' => $data['luckysix_yk'],
                'dlzy' => $data['dlzy'],
                'khyk' => $data['khyk'],
                'tmcm' => $data['tmcm'],
                'zxyk' => $data['zxyk'],
                'zxyk_zc' => $data['zxyk_zc'],
                'zhuang_zc_total' => $data['zhuang_zc_total'],
                'xian_zc_total' => $data['xian_zc_total'],      
            ];
            CardGameReport::where('card_game_id',$data['card_game_id'])->update($rUpdata);
            CardGameReportAdd::where('card_game_id',$data['card_game_id'])->update(['tongji_report'=>1]);
            echo 'card_game_id: '. $data['card_game_id'].', '.$data['boots_number'].'靴'.$data['ju'].'局;自营报表处理成功';
        }else {
            echo '暂无处理记录';
        }
    }

    /**
     * @function 处理自营报表三宝
     */
    public function doZyReportBao() {
        $data = CardGameReportAdd::where('tongji_report',0)
        ->field('he_total,zd_total,xd_total,sb_total,sbxm,sbyk,dlzy,khyk,tmcm,boots_number,ju,zhuang_total,xian_total,dc_total,ws,tm,dcyk,tmyk,wsyk,zxxm,luckysix_yk,zxyk,zxyk_zc,zhuang_zc_total,xian_zc_total,card_game_id')->find();
        if (!empty($data)){
            $rUpdata = [
                'he_total' => $data['he_total'],
                'zd_total' => $data['zd_total'],
                'xd_total' => $data['xd_total'],
                'sb_total' => $data['sb_total'],
                'sbxm' => $data['sbxm'],
                'sbyk' => $data['sbyk'],
                'dlzy' => $data['dlzy'],
                'khyk' => $data['khyk'],
                'tmcm' => $data['tmcm'],
             ];
            CardGameReport::where('card_game_id',$data['card_game_id'])->update($rUpdata);
            CardGameReportAdd::where('card_game_id',$data['card_game_id'])->update(['tongji_report'=>1]);
            echo 'card_game_id: '. $data['card_game_id'].', '.$data['boots_number'].'靴'.$data['ju'].'局;自营报表处理成功';
        }else {
            echo '暂无处理记录';
        }
    }

    /**
     * @function 增加三宝
     */
    public function addBao()
    {
        $request = Request::instance();
        $bets_log_id = $request->post('bets_merge_id');
        $zd_odds = intval($request->post('zd_odds'));
        $xd_odds = intval($request->post('xd_odds'));
        $he_odds = intval($request->post('he_odds'));
        $betslog = BetsMerge::where('id', $bets_log_id)->find();
        if (!empty($betslog)) {
            $agents_share_rate = $betslog['agents_share_rate'];
            $uid = $betslog['uid'];
            $tid = $betslog['tid'];
            $card_game_id = $betslog['card_game_id'];
            $groupid = $betslog['groupid'];
            $score_before = $betslog['score_before'];
            $game_zhuang = $betslog['game_zhuang'];
            $game_zhuang_dui = $betslog['game_zhuang_dui'];
            $game_xian_dui = $betslog['game_xian_dui'];
            $game_lucky_six = $betslog['game_lucky_six'];
            $user_zx_losewin = $betslog['user_zx_losewin'];
            $room_id = $betslog['room_id'];
            $boots_number = $betslog['boots_number'];
            $ju = $betslog['ju'];
            $zhuang = $betslog['zhuang'];
            $xian = $betslog['xian'];
            $integral_score = 0;
            $user_sb_xm = 0;
            $user_sb_losewin = 0;
            $score_change111 = 0;
            if ($game_zhuang == 1) {
                $score_change111 += $zhuang * 0.95 + $zhuang;
                $integral_score += $he_odds;
                $user_sb_losewin += -$he_odds;
                $user_sb_xm += $he_odds;
            } elseif ($game_zhuang == 2) {
                $score_change111 += $xian * 1 + $xian;
                $integral_score += $he_odds;
                $user_sb_losewin += -$he_odds;
                $user_sb_xm += $he_odds;
            } elseif ($game_zhuang == 3) {
                $user_sb_losewin += $he_odds * 8;
                $score_change111 += $zhuang + $xian + $he_odds * 8 + $he_odds;
            }

            if ($game_zhuang_dui == 1) {
                $user_sb_losewin += $zd_odds * 11;
                $score_change111 += $zd_odds * 11 + $zd_odds;
            } else {
                $integral_score += $zd_odds;
                $user_sb_losewin += -$zd_odds;
                $user_sb_xm += $zd_odds;
            }
            if ($game_xian_dui == 1) {
                $user_sb_losewin += $xd_odds * 11;
                $score_change111 += $xd_odds * 11 + $xd_odds;
            } else {
                $integral_score += $xd_odds;
                $user_sb_losewin += -$xd_odds;
                $user_sb_xm += $xd_odds;
            }

            $win = $user_sb_losewin + $user_zx_losewin;
            $score_after = $score_before + $win;
            //修改下注列表
            $mUpdateData = [
                'he' => $he_odds,
                'zhuang_dui' => $zd_odds,
                'xian_dui' => $xd_odds,
                'user_sb_losewin' => $user_sb_losewin,
                'user_sb_xm' => $user_sb_xm,
                'win' => $win,
                'score_after' => $score_after
            ];
            BetsMerge::where('id', $bets_log_id)->update($mUpdateData);
            //修改历史消息
            $text = '';
            if ($zhuang > 0) {
                $text .= '庄' . $zhuang;
            }
            if ($xian > 0) {
                $text .= '闲' . $xian;
            }
            if ($zd_odds > 0) {
                $text .= '庄对' . $zd_odds;
            }
            if ($xd_odds > 0) {
                $text .= '闲对' . $xd_odds;
            }
            if ($he_odds > 0) {
                $text .= '和' . $he_odds;
            }
            ChatPacket::where('fromuid', $uid)->where('card_game_id', $card_game_id)->update(['message' => $text]);
            //修改投注表
            $chatPacket = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->field('message')->find();
            $chatPacketArr = json_decode($chatPacket['message'], true);
            $zd_odds_total = 0;
            $xd_odds_total = 0;
            $he_odds_total = 0;
            foreach ($chatPacketArr[0] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item[3] = $he_odds;
                    $item[4] = $zd_odds;
                    $item[5] = $xd_odds;
                    $item['h'] = $xd_odds;
                    $item['zd'] = $zd_odds;
                    $item['xd'] = $xd_odds;
                    $zd_odds_total += $zd_odds;
                    $xd_odds_total += $xd_odds;
                    $he_odds_total += $he_odds;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $he_odds_total += $item[3];
                    $zd_odds_total += $item[4];
                    $xd_odds_total += $item[5];
                }
                if ($item['key'] == -1) {
                    $item[3] = $he_odds_total;
                    $item[4] = $zd_odds_total;
                    $item[5] = $xd_odds_total;
                    $item['h'] = $he_odds_total;
                    $item['zd'] = $zd_odds_total;
                    $item['xd'] = $xd_odds_total;
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->update(['message' => json_encode($chatPacketArr)]);
            //修改余分表
            $chatPacketyf = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->field('message')->find();
            $chatPacketArryf = json_decode($chatPacketyf['message'], true);
            $yf_total = 0;
            $win_total = 0;
            foreach ($chatPacketArryf['data'] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item['score'] = $score_after;
                    $item['win'] = $win;
                    $yf_total += $score_after;
                    $win_total += $win;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $yf_total += $item['score'];
                    $win_total += $item['win'];
                }
                if ($item['key'] == -1) {
                    $item['score'] = $yf_total;
                    $item['win'] = $win_total;
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->update(['message' => json_encode($chatPacketArryf)]);

            //修改牌局报表
            $report = CardGameReport::where('card_game_id', $card_game_id)->find();
            if (!empty($report)) {
                $config = TeamRoom::where('groupid', $groupid)->field('mantissa,odds_zx_min')->find();
                $mantissa = intval($config['mantissa']);
                $dcyk = $report['dcyk'];
                $tmyk = $report['tmyk'];
                $wsyk = $report['wsyk'];
                $luckysix_yk = $report['luckysix_yk'];
                $zd_total = $report['zd_total'];
                $xd_total = $report['xd_total'];
                $he_total = $report['he_total'];
                $lucky_total = $report['lucky_six_total'];
                $sb_total = $report['sb_total'];
                $zd_total += $zd_odds;
                $xd_total += $xd_odds;
                $he_total += $he_odds;
                $sb_total = $sb_total + $zd_odds + $xd_odds + $he_odds;
                $sbyk = 0;
                $sbxm = 0;
                if ($game_zhuang_dui > 0) {
                    $sbyk -= $zd_total * 11;
                } else {
                    $sbxm += $zd_total;
                    $sbyk += $zd_total;
                }
                if ($game_xian_dui > 0) {
                    $sbyk -= $xd_total * 11;
                } else {
                    $sbxm += $xd_total;
                    $sbyk += $xd_total;
                }
                if ($game_zhuang == 3) {
                    $sbyk -= $he_total * 8;
                } else {
                    $sbxm += $he_total;
                    $sbyk += $he_total;
                }
                if ($game_lucky_six == 0) {
                    $sbxm += $lucky_total;
                    // $sbyk += $lucky_total;
                } elseif ($game_lucky_six == 6) {
                    //  $sbyk -= $lucky_total * 12;
                } elseif ($game_lucky_six == 7) {
                    // $sbyk -= $lucky_total * 20;
                }

                //统计代理总赢
                $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
                //客户盈亏
                $khyk = -($wsyk + $sbyk + $dcyk + $tmyk + $luckysix_yk);
                //台面筹码
                $tmcm = $dlzy + $khyk;

                $rUpdata = [
                    'he_total' => $he_total,
                    'zd_total' => $zd_total,
                    'xd_total' => $xd_total,
                    'sb_total' => $sb_total,
                    'sbxm' => $sbxm,
                    'sbyk' => $sbyk,
                    'dlzy' => $dlzy,
                    'khyk' => $khyk,
                    'tmcm' => $tmcm,
                ];
                CardGameReportAdd::create($rUpdata);
               // CardGameReport::where('card_game_id', $card_game_id)->update($rUpdata);
            }

            //修改余分流水表
            $userLog = UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)
                ->where('type', '<>', 111)->field('score_after,time,agents_id,tid')->find();
            $userLog_after = $userLog['score_after'];
            $userLog_time = $userLog['time'];
            $userLog_agents_id = $userLog['agents_id'];
            $userLog_tid = $userLog['tid'];
            if ($he_odds > 0) {
                $insertData = [
                    'uid' => $uid,
                    'score' => $userLog_after,
                    'score_change' => -$he_odds,
                    'score_after' => $userLog_after - $he_odds,
                    'type' => 3,
                    'card_game_id' => $card_game_id,
                    'orderid' => 0,
                    'ukey' => 'odds_' . $uid . '_3_' . time(),
                    'time' => $userLog_time + 1,
                    'agents_id' => $userLog_agents_id,
                    'tid' => $userLog_tid,
                    'game_type' => 0,
                    'note' => $room_id . '桌-' . $boots_number . '靴-' . $ju . '局 和' . $he_odds
                ];
                UserScoreLog::create($insertData);
                $userLog_after = $userLog_after - $he_odds;
            }

            if ($zd_odds > 0) {
                $insertData = [
                    'uid' => $uid,
                    'score' => $userLog_after,
                    'score_change' => -$zd_odds,
                    'score_after' => $userLog_after - $zd_odds,
                    'type' => 4,
                    'card_game_id' => $card_game_id,
                    'orderid' => 0,
                    'ukey' => 'odds_' . $uid . '_4_' . time(),
                    'time' => $userLog_time + 1,
                    'agents_id' => $userLog_agents_id,
                    'tid' => $userLog_tid,
                    'game_type' => 0,
                    'note' => $room_id . '桌-' . $boots_number . '靴-' . $ju . '局 庄对' . $zd_odds
                ];
                UserScoreLog::create($insertData);
                $userLog_after = $userLog_after - $zd_odds;
            }

            if ($xd_odds > 0) {
                $insertData = [
                    'uid' => $uid,
                    'score' => $userLog_after,
                    'score_change' => -$xd_odds,
                    'score_after' => $userLog_after - $xd_odds,
                    'type' => 5,
                    'card_game_id' => $card_game_id,
                    'orderid' => 0,
                    'ukey' => 'odds_' . $uid . '_5_' . time(),
                    'time' => $userLog_time + 2,
                    'agents_id' => $userLog_agents_id,
                    'tid' => $userLog_tid,
                    'game_type' => 0,
                    'note' => $room_id . '桌-' . $boots_number . '靴-' . $ju . '局 闲对' . $xd_odds
                ];
                UserScoreLog::create($insertData);
                $userLog_after = $userLog_after - $xd_odds;
            }
            $userLog02 = UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', 111)->find();
            if (!empty($userLog02)) {
                UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', 111)->update(
                    ['score' => $userLog_after, 'score_change' => $score_change111, 'score_after' => $score_after]
                );
            } else {
                $insertData02 = [
                    'uid' => $uid,
                    'score' => $userLog_after,
                    'score_change' => $score_change111,
                    'score_after' => $score_after,
                    'type' => 111,
                    'card_game_id' => $card_game_id,
                    'orderid' => 0,
                    'ukey' => 'odds_' . $uid . '_111_' . time(),
                    'time' => $userLog_time + 3,
                    'agents_id' => $userLog_agents_id,
                    'tid' => $userLog_tid,
                    'game_type' => 0,
                    'note' => $room_id . '桌-' . $boots_number . '靴-' . $ju . '局 牌局结算'
                ];
                UserScoreLog::create($insertData02);
            }
            //修改user余分
            User::where('uid', $uid)->update(['score' => $score_after]);
            //修改积分记录
            $tconfig = TeamConfig::where('tid', $tid)->find();
            $score_rate = $tconfig['score_rate'];
            $integral = 0;
            if ($score_rate > 0) {
                $integral = common::math_div($integral_score, $score_rate, 2);
            }
            $datetime = date('Ymd', time());
            $intedate = IntegralDate::where('uid', $uid)->where('date_time', $datetime)->find();
            $dateinte = $intedate['integral'] + $integral;
            $integral_total = $intedate['integral_total'] + $integral;
            IntegralDate::where('uid', $uid)->where('date_time', $datetime)->update(['integral' => $dateinte, 'integral_total' => $integral_total]);
            echo '修改完成';
        }
    }


    /**
     * @function 修改注码
     */
    public function changeBets()
    {
        $request = Request::instance();
        $bets_log_id = $request->post('bets_log_id');
        $odds = $request->post('odds');
        $odds_type = $request->post('odds_type');
        $betslog = BetsLog::where('id', $bets_log_id)->find();
        if (!empty($betslog)) {
            $agents_share_rate = 0;
            $uid = $betslog['uid'];
            $tid = $betslog['tid'];
            //获取会员信息
            $user = User::where('uid', $uid)->field('agents_share_rate')->find();
            if (!empty($user)) {
                $agents_share_rate = $user['agents_share_rate'];
            }
            $card_game_id = $betslog['card_game_id'];
            $groupid = $betslog['groupid'];
            $type = $betslog['type'];
            $score_before = $betslog['score_before'];
            $odds_old = $betslog['odds'];
            $zhuang = 0;
            $xian = 0;
            $zhuang_yx = 0;
            $xian_yx = 0;
            $zxyk = 0;
            $zxyk_zc = 0;
            $game = CardGame::where('id', $card_game_id)->field('zhuang')->find();
            if ($game == 3) {
                echo '开的是和啊';
                exit();
            }
            if ($odds_type == 1) {
                $text = '庄' . $odds;
                $rate = 0.95;
                $win = $odds * $rate;
                $score_after = $score_before + $win;
                $zhuang = $odds;
            } else {
                $text = '闲' . $odds;
                $rate = 1;
                $win = $odds;
                $score_after = $score_before + $win;
                $xian = $odds;
            }
            $updateData = [
                'text' => $text,
                'odds' => $odds,
                'xm' => $odds,
                'win' => $win,
                'score_after' => $score_after
            ];
            BetsLog::where('id', $bets_log_id)->update($updateData);
            //修改投注记录
            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid;
            $mData = BetsMerge::where('ukey', $bets_ukey)->find();
            if (!empty($mData)) {
                $mUpdateData = [
                    'zhuang' => $zhuang,
                    'xian' => $xian,
                    'win' => $win,
                    'score_after' => $score_after,
                    'user_zx_xm' => $odds,
                    'user_zx_losewin' => $win,
                    'zhuang_yx' => $zhuang_yx,
                    'xian_yx' => $xian_yx,
                    'user_zx_losewin_yx' => $win
                ];
                BetsMerge::where('ukey', $bets_ukey)->update($mUpdateData);
            }
            //修改牌局报表
            $report = CardGameReport::where('card_game_id', $card_game_id)->find();
            if (!empty($report)) {
                $config = TeamRoom::where('groupid', $groupid)->field('mantissa,odds_zx_min')->find();
                $mantissa = intval($config['mantissa']);
                $zhuang_total = $report['zhuang_total'];
                $xian_total = $report['xian_total'];
                $zhuang_zc_total = $report['zhuang_zc_total'];
                $xian_zc_total = $report['xian_zc_total'];

                $dc_total = 0;
                if ($type == 1) {
                    $zhuang_total = $zhuang_total - $odds_old;
                    $zhuang_zc_total -= $odds_old * $agents_share_rate / 10;
                } elseif ($type == 2) {
                    $xian_total = $xian_total - $odds_old;
                    $xian_zc_total -= $odds_old * $agents_share_rate / 10;
                }

                if ($odds_type == 1) {
                    $zhuang_total = $zhuang_total + $odds;
                    $zhuang_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($odds_type == 2) {
                    $xian_total = $xian_total + $odds;
                    $xian_zc_total += $odds * $agents_share_rate / 10;
                }

                if ($zhuang_total > $xian_total) {
                    $dc_total = $xian_total * $agents_share_rate / 10;
                } elseif ($zhuang_total < $xian_total) {
                    $dc_total = $zhuang_total * $agents_share_rate / 10;
                }
                $tm = 0;
                $ws = 0;
                $dcyk = 0;
                $tmyk = 0;
                $wsyk = 0;
                $zxxm = 0;
                $sbyk = $report['sbyk'];
                if ($zhuang_total != 0 || $xian_total != 0) {
                    if ($mantissa > 0) {
                        $tm = floor(abs($zhuang_total - $xian_total - ($zhuang_total - $xian_total) * $agents_share_rate / 10) / $mantissa) * $mantissa;
                    } elseif ($mantissa == 0) {
                        $tm = abs($zhuang_total - $xian_total - ($zhuang_total - $xian_total) * $agents_share_rate / 10);
                    }
                    if ($tm < $config['odds_zx_min']) {
                        $tm = 0;
                    }
                    $ws = abs($zhuang_total - $xian_total) * $agents_share_rate / 10 - $tm;
                }
                if ($game['zhuang'] == 1) {
                    $dcyk = $dc_total - ($dc_total * 0.95);
                }
                if ($zhuang_total > $xian_total) {
                    if ($game['zhuang'] == 1) {
                        $wsyk = -($ws * 0.95);
                        $tmyk = -($tm * 0.95);
                        $zxyk = $xian_total - $zhuang_total * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $zhuang_total - $xian_total;
                    }
                } elseif ($zhuang_total < $xian_total) {
                    if ($game['zhuang'] == 1) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $xian_total - $zhuang_total * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $wsyk = -$ws;
                        $tmyk = -$tm;
                        $zxyk = $zhuang_total - $xian_total;
                    }
                }
                $zxyk_zc = $zxyk * $agents_share_rate / 10;
                //统计代理总赢
                $dlzy = $wsyk + $sbyk + $dcyk;
                //客户盈亏
                $khyk = -($wsyk + $sbyk + $dcyk + $tmyk);
                //台面筹码
                $tmcm = $dlzy + $khyk;

                $rUpdata = [
                    'zhuang_total' => $zhuang_total,
                    'xian_total' => $xian_total,
                    'dc_total' => $dc_total,
                    'ws' => $ws,
                    'tm' => $tm,
                    'dcyk' => $dcyk,
                    'tmyk' => $tmyk,
                    'wsyk' => $wsyk,
                    'zxxm' => $zxxm,
                    'sbyk' => $sbyk,
                    'dlzy' => $dlzy,
                    'khyk' => $khyk,
                    'tmcm' => $tmcm,
                    'zxyk' => $zxyk,
                    'zxyk_zc' => $zxyk_zc,
                    'zhuang_zc_total' => $zhuang_zc_total,
                    'xian_zc_total' => $xian_zc_total
                ];
                CardGameReport::where('card_game_id', $card_game_id)->update($rUpdata);
            }
            //修改历史消息
            ChatPacket::where('fromuid', $uid)->where('card_game_id', $card_game_id)->update(['message' => $text]);
            //修改投注表
            $chatPacket = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->field('message')->find();
            $chatPacketArr = json_decode($chatPacket['message'], true);
            $odds_total = 0;
            foreach ($chatPacketArr[0] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item[1] = 0;
                    $item[2] = 0;
                    $item['z'] = 0;
                    $item['x'] = 0;
                    $item[$odds_type] = $odds;
                    if ($odds_type == 1) {
                        $item['z'] = $odds;
                    } elseif ($odds_type == 2) {
                        $item['x'] = $odds;
                    }
                    $item['win'] = -$odds;
                    $odds_total += $odds;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $odds_total += $item[$odds_type];
                }

                if ($item['key'] == -1) {
                    $item[$odds_type] = $odds_total;
                    $item[$type] -= $odds_old;
                    if ($odds_type == 1) {
                        $item['z'] = $odds_total;
                        $item['x'] -= $odds_old;
                    } elseif ($odds_type == 2) {
                        $item['x'] = $odds_total;
                        $item['z'] -= $odds_old;
                    }
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->update(['message' => json_encode($chatPacketArr)]);
            //修改余分表
            $chatPacketyf = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->field('message')->find();
            $chatPacketArryf = json_decode($chatPacketyf['message'], true);
            $yf_total = 0;
            $win_total = 0;
            $yf_after = $score_before + $win;
            foreach ($chatPacketArryf['data'] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item['score'] = $yf_after;
                    $item['win'] = $win;
                    $yf_total += $yf_after;
                    $win_total += $win;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $yf_total += $item['score'];
                    $win_total += $item['win'];
                }
                if ($item['key'] == -1) {
                    $item['score'] = $yf_total;
                    $item['win'] = $win_total;
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->update(['message' => json_encode($chatPacketArryf)]);
            //修改余分流水表
            $usLog = UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->select();
            $score_after_us1 = 0;
            $us_chanage = 0;
            foreach ($usLog as $v1) {
                if ($v1['type'] == $type) {
                    $score_us = $v1['score'];
                    $score_change_us = -$odds;
                    $score_after_us = $score_us - $odds;
                    $score_after_us1 = $score_after_us;
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', $type)
                        ->update(['score' => $score_us, 'score_change' => $score_change_us, 'score_after' => $score_after_us, 'type' => $odds_type]);
                }
                if ($v1['type'] == 111) {
                    if ($game['zhuang'] == 1) {
                        $us_chanage = $odds + $odds * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $us_chanage = $odds + $odds * 1;
                    }
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', 111)
                        ->update(['score' => $score_after_us1, 'score_change' => $us_chanage, 'score_after' => $yf_after]);
                }
            }
            //修改user余分
            User::where('uid', $uid)->update(['score' => $yf_after]);
            //修改积分记录
            $tconfig = TeamConfig::where('tid', $tid)->find();
            $score_rate = $tconfig['score_rate'];
            $integral = 0;
            if ($score_rate > 0) {
                $integral = common::math_div($odds, $score_rate, 2);
            }
            $ilog = IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)->field('integral')->find();
            $cinte = $integral - $ilog['integral'];
            IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)
                ->update(['score' => $odds, 'integral' => $integral]);
            $datetime = date('Ymd', time());
            $intedate = IntegralDate::where('uid', $uid)->where('date_time', $datetime)->find();
            $dateinte = $intedate['integral'] + $cinte;
            $integral_total = $intedate['integral_total'] + $cinte;
            IntegralDate::where('uid', $uid)->where('date_time', $datetime)->update(['integral' => $dateinte, 'integral_total' => $integral_total]);
            echo '修改完成';
        } else {
            echo '无下注记录';
        }
    }

    /**
     * @function 插入注码
     */

    public function insertOdds()
    {
        $request = Request::instance();
        $odds_type = intval($request->post('odds_type'));
        $odds = intval($request->post('odds'));
        $card_game_id = $request->post('card_game_id');
        $text = '';

        if ($odds_type == 1) {

        }

    }

    /**
     * @function 检查报表
     */
    public function checkReport()
    {
        // select * from  `card_game_report` where  `mktime`>=1618977600 and mktime<=1618999200

        set_time_limit(0);
        $datas = CardGameReport::where('check_report', 0)->where('tid', 5)->limit(50)->order('id', 'desc')->select();
        foreach ($datas as $item) {
            $id = $item['id'];
            $tmyk = $item['tmyk'];
            $dlzy = $item['dlzy'];
            $khyk = $item['khyk'];
            $zxyk_zc = $item['zxyk_zc'];
            $card_game_id = $item['card_game_id'];
            // $game = CardGame::where('id', $card_game_id)->field('bets_merge_state')->find();
//            if ($game['bets_merge_state'] ==0){
//                continue;
//            }
//            if ($tmyk + $dlzy + $khyk > 10) {
//                echo 'card_game_id:' . $card_game_id . '||' . PHP_EOL;
//            }
            // $win = 0;
            //  $bets = BetsMerge::where('card_game_id', $card_game_id)->field('SUM(win) as win')->find()->toArray();
            $win = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->sum('win');
            // echo BetsMerge::getLastSql() . '||';
            // $win = $bets['win'];
            //  print_r($bets);exit;
            if ($win - ($khyk - $zxyk_zc) > 10 || $win - ($khyk - $zxyk_zc) < 10) {
                //echo 'bets-merge:' . $win . ' | khyk:' . $khyk . ' | zxyk_zc:' . $zxyk_zc . ' | card_game_id:' . $card_game_id . '||' . PHP_EOL;
                echo ' | card_game_id:' . $card_game_id . '数据有误||' . PHP_EOL;
            }
            echo 'bets-merge:' . $win . ' | khyk:' . $khyk . ' | zxyk_zc:' . $zxyk_zc . ' | card_game_id:' . $card_game_id . '||' . PHP_EOL;

            CardGameReport::where('id', $id)->update(['check_report' => 1]);
        }
        echo '执行完毕';
    }

    /**
     * @function 修订统计报表
     */
    public function updateReport()
    {
        $datas = CardGameReport::where('tongji_report', 0)->limit(50)->select();
        foreach ($datas as $report) {
            $khyk = $report['khyk'];
            $zxyk_zc = $report['zxyk_zc'];
            $card_game_id = $report['card_game_id'];
//            $game = CardGame::where('id', $card_game_id)->field('bets_merge_state')->find();
//            if ($game['bets_merge_state'] == 0) {
//                continue;
//            }
            $sbyk = $report['sbyk'];
            $luckysix_yk = $report['luckysix_yk'];
            //$bets = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
            $win = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->sum('win');
            //$report = CardGameReport::where('card_game_id', $card_game_id)->field('sbyk,luckysix_yk')->find();
            $cardGame = CardGame::where('id', $card_game_id)->field('groupid,zhuang,zhuang_dui,xian_dui,lucky_six')->find();
            $config = TeamRoom::where('groupid', $cardGame['groupid'])->field('mantissa,odds_zx_min')->find();
            $mantissa = $config['mantissa'];
            $betlogs = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->select();
            $zhuang_total = 0;
            $zhuang_zc_total = 0;
            $xian_total = 0;
            $xian_zc_total = 0;
            $zd_total = 0;
            $xd_total = 0;
            $he_total = 0;
            $sb_total = 0;
            $lucky_six_total = 0;
            $ws = 0;
            $tm = 0;
            $dcyk = 0;
            $wsyk = 0;
            $tmyk = 0;
            $zxyk = 0;
            $zxxm = 0;
            $zxyk_zc = 0;
            foreach ($betlogs as $item) {
                $type = $item['type'];
                $odds = $item['odds'];
                $agents_share_rate = $item['agents_share_rate'];
                if ($type == 1) {
                    $zhuang_total += $odds;
                    $zhuang_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 2) {
                    $xian_total += $odds;
                    $xian_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 3) {
                    $he_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 4) {
                    $zd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 5) {
                    $xd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 7) {
                    $lucky_six_total += $odds;
                }
            }
            
            $zhuang_total_last = $zhuang_total - $zhuang_zc_total; 
            $xian_total_last = $xian_total - $xian_zc_total; 
            $dc_total = ceil(min([$zhuang_total_last, $xian_total_last]));
            if ($zhuang_total_last != 0 || $xian_total_last != 0) {
                if ($mantissa > 0) { 
                    $tm = floor(abs(ceil($zhuang_total_last - $xian_total_last)) / $mantissa) * $mantissa;
                } elseif ($mantissa == 0) {
                    $tm = abs($zhuang_total_last - $xian_total_last);
                }
                if ($tm < $config['odds_zx_min']) {
                    $tm = 0;
                }
                $ws = abs($zhuang_total_last - $xian_total_last) - $tm;
            }
            if ($cardGame['zhuang'] == 1) {
                $dcyk = $dc_total - ($dc_total * 0.95);
            }
            if ($zhuang_total_last > $xian_total_last) {//推庄
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = -($ws * 0.95);
                    $tmyk = -($tm * 0.95);
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            } elseif ($zhuang_total_last < $xian_total_last) {//推闲
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = -$ws;
                    $tmyk = -$tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            }

            if ($cardGame['zhuang'] == 1) {
                $zxyk_zc = $xian_zc_total - $zhuang_zc_total * 0.95;
            } elseif ($cardGame['zhuang'] == 2) {
                $zxyk_zc = $zhuang_zc_total - $xian_zc_total;
            }
            //统计代理总赢
            $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
            //客户盈亏
            $khyk = -($dlzy + $tmyk);
            //台面筹码
            $tmcm = $dlzy + $khyk;

            $updateData = [
                'dc_total' => round($dc_total,2),
                'dcyk' => round($dcyk,2), 
                'ws' => round($ws,2),
                'tm' => round($tm,2),
                'wsyk' => round($wsyk,2),
                'tmyk' => round($tmyk,2),
                'dlzy' => round($dlzy,2),
                'khyk' => round($khyk,2),
                'tmcm' => round($tmcm,2),
                'zxyk_zc' =>round($zxyk_zc),
                'zhuang_zc_total' => round($zhuang_zc_total,2),
                'xian_zc_total' => round($xian_zc_total,2),
                'lq_total' => round($zhuang_total_last,2),
                'fb_total' => round($xian_total_last,2),
                'tongji_report' => 1
            ];
            CardGameReport::where('card_game_id', $card_game_id)->update($updateData);
            echo 'card_game_id:' . $card_game_id . '修改数据：' .json_encode($updateData). PHP_EOL;
            $resportnew = CardGameReport::where('card_game_id', $card_game_id)->field('khyk,zxyk_zc')->find();
            if($updateData['khyk'] != $resportnew['khyk'] || $updateData['zxyk_zc'] != $resportnew['zxyk_zc']){
                CardGameReport::where('card_game_id', $card_game_id)->update(['tongji_report'=>0]);
                echo 'card_game_id:' . $card_game_id . '数据修改失败||;' . PHP_EOL;exit();
            }
            if (abs($win - ($khyk - $zxyk_zc) > 10)) {
                echo 'card_game_id:' . $card_game_id . '数据有误||执行完毕;' . PHP_EOL;
            } else {
                               
                echo 'card_game_id:' . $card_game_id . '数据正常||执行完毕' . PHP_EOL;
            }
        }
    }
    
    /**
     * @function 修订统计报表
     */
    public function jiliupdateReport()
    {
        $datas = CardGameReport::where('tongji_report', 1)->where('tongji_report_again', 0)->limit(50)->select();
        foreach ($datas as $report) {
            $khyk = $report['khyk'];
            $zxyk_zc = $report['zxyk_zc'];
            $card_game_id = $report['card_game_id'];
            //            $game = CardGame::where('id', $card_game_id)->field('bets_merge_state')->find();
            //            if ($game['bets_merge_state'] == 0) {
            //                continue;
            //            }
            $sbyk = $report['sbyk'];
            $luckysix_yk = $report['luckysix_yk'];
            //$bets = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
            $win = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->sum('win');
            //$report = CardGameReport::where('card_game_id', $card_game_id)->field('sbyk,luckysix_yk')->find();
            $cardGame = CardGame::where('id', $card_game_id)->field('groupid,zhuang,zhuang_dui,xian_dui,lucky_six')->find();
            $config = TeamRoom::where('groupid', $cardGame['groupid'])->field('mantissa,odds_zx_min')->find();
            $mantissa = $config['mantissa'];
            $betlogs = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->select();
            $zhuang_total = 0;
            $zhuang_zc_total = 0;
            $xian_total = 0;
            $xian_zc_total = 0;
            $zd_total = 0;
            $xd_total = 0;
            $he_total = 0;
            $sb_total = 0;
            $lucky_six_total = 0;
            $ws = 0;
            $tm = 0;
            $dcyk = 0;
            $wsyk = 0;
            $tmyk = 0;
            $zxyk = 0;
            $zxxm = 0;
            $zxyk_zc = 0;
            foreach ($betlogs as $item) {
                $type = $item['type'];
                $odds = $item['odds'];
                $agents_share_rate = $item['agents_share_rate'];
                if ($type == 1) {
                    $zhuang_total += $odds;
                    $zhuang_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 2) {
                    $xian_total += $odds;
                    $xian_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 3) {
                    $he_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 4) {
                    $zd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 5) {
                    $xd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 7) {
                    $lucky_six_total += $odds;
                }
            }
            
            $zhuang_total_last = $zhuang_total - $zhuang_zc_total;
            $xian_total_last = $xian_total - $xian_zc_total;
            $dc_total = ceil(min([$zhuang_total_last, $xian_total_last]));
            if ($zhuang_total_last != 0 || $xian_total_last != 0) {
                if ($mantissa > 0) {
                    $tm = floor(abs(ceil($zhuang_total_last - $xian_total_last)) / $mantissa) * $mantissa;
                } elseif ($mantissa == 0) {
                    $tm = abs($zhuang_total_last - $xian_total_last);
                }
                if ($tm < $config['odds_zx_min']) {
                    $tm = 0;
                }
                $ws = abs($zhuang_total_last - $xian_total_last) - $tm;
            }
            if ($cardGame['zhuang'] == 1) {
                $dcyk = $dc_total - ($dc_total * 0.95);
            }
            if ($zhuang_total_last > $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = -($ws * 0.95);
                    $tmyk = -($tm * 0.95);
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            } elseif ($zhuang_total_last < $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = -$ws;
                    $tmyk = -$tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            }
            
            if ($cardGame['zhuang'] == 1) {
                $zxyk_zc = $xian_zc_total - $zhuang_zc_total * 0.95;
            } elseif ($cardGame['zhuang'] == 2) {
                $zxyk_zc = $zhuang_zc_total - $xian_zc_total;
            }
            //统计代理总赢
            $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
            //客户盈亏
            $khyk = -($dlzy + $tmyk);
            //台面筹码
            $tmcm = $dlzy + $khyk;
            
            CardGameReport::where('card_game_id', $card_game_id)->update([
                'dc_total' => round($dc_total,2),
                'dcyk' => round($dcyk,2),
                'ws' => round($ws,2),
                'tm' => round($tm,2),
                'wsyk' => round($wsyk,2),
                'tmyk' => round($tmyk,2),
                'dlzy' => round($dlzy,2),
                'khyk' => round($khyk,2),
                'tmcm' => round($tmcm,2),
                'zxyk_zc' =>round($zxyk_zc,2),
                'zhuang_zc_total' => round($zhuang_zc_total,2),
                'xian_zc_total' => round($xian_zc_total,2),
                'lq_total' => round($zhuang_total_last,2),
                'fb_total' => round($xian_total_last,2),
                'tongji_report_again' =>1
            ]);
            if (abs($win - ($khyk - $zxyk_zc)) > 10 ) {
                echo 'card_game_id:' . $card_game_id . '数据有误||重新统计，执行完毕' . PHP_EOL;
            } else {
                echo 'card_game_id:' . $card_game_id . '数据正常||重新统计，执行完毕' . PHP_EOL;
            }     
        }
    }

    /**
     * @function 麒麟重新统计
     */
    public function updateReportAgain() {
        $data = CardGameReport::where('tongji_report', 1)->where('tongji_report_again',0)->where('guiling',0)->limit(50)->field('id,mktime')->select();
        $curtime = time();
        if (count($data)>0) {
            foreach ($data as $item){
                if ($curtime -$item['mktime']>=300) {
                    CardGameReport::where('id', $item['id'])->update(['tongji_report'=>0,'tongji_report_again'=>1]) ;
                    echo 'id:' . $item['id'] . '执行重新统计||' . PHP_EOL;
                }
            }
        }else{
            echo '暂无记录:'.date('Y-m-d H:i:s');
        }
    }
    /**
     * @function 环球修订统计报表
     */
    public function updateReportHQ()
    {
        $datas = CardGameReport::where('tongji_report', 0)->limit(50)->select();
        foreach ($datas as $report) {
            $khyk = $report['khyk'];
            $zxyk_zc = $report['zxyk_zc'];
            $card_game_id = $report['card_game_id'];
//            $game = CardGame::where('id', $card_game_id)->field('bets_merge_state')->find();
//            if ($game['bets_merge_state'] == 0) {
//                continue;
//            }
            $sbyk = $report['sbyk'];
            $luckysix_yk = $report['luckysix_yk'];
            //$bets = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
            $win = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->sum('win');
            //$report = CardGameReport::where('card_game_id', $card_game_id)->field('sbyk,luckysix_yk')->find();
            $cardGame = CardGame::where('id', $card_game_id)->field('groupid,zhuang,zhuang_dui,xian_dui,lucky_six')->find();
            $config = TeamRoom::where('groupid', $cardGame['groupid'])->field('mantissa,odds_zx_min')->find();
            $mantissa = $config['mantissa'];
            $betlogs = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->select();
            $zhuang_total = 0;
            $zhuang_zc_total = 0;
            $xian_total = 0;
            $xian_zc_total = 0;
            $zd_total = 0;
            $xd_total = 0;
            $he_total = 0;
            $sb_total = 0;
            $lucky_six_total = 0;
            $ws = 0;
            $tm = 0;
            $dcyk = 0;
            $wsyk = 0;
            $tmyk = 0;
            $zxyk = 0;
            $zxxm = 0;
            $zxyk_zc = 0;
            foreach ($betlogs as $item) {
                $type = $item['type'];
                $odds = $item['odds'];
                $agents_share_rate = $item['agents_share_rate'];
                if ($type == 1) {
                    $zhuang_total += $odds;
                    $zhuang_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 2) {
                    $xian_total += $odds;
                    $xian_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 3) {
                    $he_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 4) {
                    $zd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 5) {
                    $xd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 7) {
                    $lucky_six_total += $odds;
                }
            }
            
            $zhuang_total_last = $zhuang_total - $zhuang_zc_total;
            $xian_total_last = $xian_total - $xian_zc_total;
            $dc_total = min([$zhuang_total, $xian_total]);
            if ($zhuang_total_last != 0 || $xian_total_last != 0) {
                if ($mantissa > 0) {
                    $tm = floor(abs(ceil($zhuang_total_last - $xian_total_last)) / $mantissa) * $mantissa;
                } elseif ($mantissa == 0) {
                    $tm = abs($zhuang_total_last - $xian_total_last);
                }
                if ($tm < $config['odds_zx_min']) {
                    $tm = 0;
                }
                $ws = abs($zhuang_total_last - $xian_total_last) - $tm;
            }
            if ($cardGame['zhuang'] == 1) {
                $dcyk = $dc_total - ($dc_total * 0.95);
            }
            if ($zhuang_total_last > $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = -($ws * 0.95);
                    $tmyk = -($tm * 0.95);
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            } elseif ($zhuang_total_last < $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = -$ws;
                    $tmyk = -$tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            }

            if ($cardGame['zhuang'] == 1) {
                $zxyk_zc = $xian_zc_total - $zhuang_zc_total * 0.95;
            } elseif ($cardGame['zhuang'] == 2) {
                $zxyk_zc = $zhuang_zc_total - $xian_zc_total;
            }
            //统计代理总赢
            $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
            //客户盈亏
            $khyk = -($dlzy + $tmyk);
            //台面筹码
            $tmcm = $dlzy + $khyk;

            CardGameReport::where('card_game_id', $card_game_id)->update([
                'dc_total' => $dc_total,
                'dcyk' => $dcyk,
                'ws' => $ws,
                'tm' => $tm,
                'wsyk' => $wsyk,
                'tmyk' => $tmyk,
                'dlzy' => $dlzy,
                'khyk' => $khyk,
                'tmcm' => $tmcm,
                'zxyk_zc' => $zxyk_zc,
                'zhuang_zc_total' => $zhuang_zc_total,
                'xian_zc_total' => $xian_zc_total,
                'lq_total' => $zhuang_total_last,
                'fb_toal' =>$xian_total_last
            ]);
            if ($win - ($khyk - $zxyk_zc) > 10 || $win - ($khyk - $zxyk_zc) < 10) {
                echo 'card_game_id:' . $card_game_id . '数据有误||执行完毕' . PHP_EOL;
            } else {
                echo 'card_game_id:' . $card_game_id . '数据正常||执行完毕' . PHP_EOL;
            }
            CardGameReport::where('card_game_id', $card_game_id)->update(['tongji_report' => 1]);
        }
    }

    /**
     * @functiion 麒麟加注
     */
    public function addBetsQilin()
    {
        set_time_limit(0);
        $request = Request::instance();
        $bets_log_id = $request->post('bets_log_id');
        $odds = $request->post('odds');
        $betslog = BetsLog::where('id', $bets_log_id)->find();
        if (!empty($betslog)) {
            $uid = $betslog['uid'];
            $tid = $betslog['tid'];
            $card_game_id = $betslog['card_game_id'];
            $groupid = $betslog['groupid'];
            $type = $betslog['type'];
            $score_before = $betslog['score_before'];
            $odds_old = $betslog['odds'];
            if ($odds - $odds_old <= 0) {
                echo '重复执行';
                exit;
            }
            $zhuang = 0;
            $xian = 0;
            $zhuang_yx = 0;
            $xian_yx = 0;
            $zxyk = 0;
            $zxyk_zc = 0;
            $game = CardGame::where('id', $card_game_id)->field('zhuang,groupid,boots_number,ju')->find();
            $config = TeamRoom::where('groupid', $game['groupid'])->field('mantissa,odds_zx_min,mark')->find();
            if ($game['zhuang'] == 3) {
                echo '开的是和啊';
                exit;
            }
            if ($type == 1) {
                $text = '庄' . $odds;
                $rate = 0.95;
                $win = $odds * $rate;
                $score_after = $score_before + $win;
                $zhuang = $odds;
            } else {
                $text = '闲' . $odds;
                $rate = 1;
                $win = $odds;
                $score_after = $score_before + $win;
                $xian = $odds;
            }
            $updateData = [
                'text' => $text,
                'odds' => $odds,
                'xm' => $odds,
                'win' => $win,
                'score_after' => $score_after
            ];
            BetsLog::where('id', $bets_log_id)->update($updateData);
            //修改投注记录
            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid;
            $mData = BetsMerge::where('ukey', $bets_ukey)->find();
            if (!empty($mData)) {
                $mUpdateData = [
                    'zhuang' => $zhuang,
                    'xian' => $xian,
                    'win' => $win,
                    'score_after' => $score_after,
                    'user_zx_xm' => $odds,
                    'user_zx_losewin' => $win,
                    'zhuang_yx' => $zhuang_yx,
                    'xian_yx' => $xian_yx,
                    'user_zx_losewin_yx' => $win
                ];
                BetsMerge::where('ukey', $bets_ukey)->update($mUpdateData);
            }
            //修改牌局报表
            $report = CardGameReport::where('card_game_id', $card_game_id)->find();
            $sbyk = $report['sbyk'];
            $luckysix_yk = $report['luckysix_yk'];
            if (!empty($report)) {
                $cardGame = CardGame::where('id', $card_game_id)->field('groupid,zhuang,zhuang_dui,xian_dui,lucky_six,boots_number,ju')->find();
                $mantissa = $config['mantissa'];
                $betlogs = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->where('tourist', 0)->select();
                $zhuang_total = 0;
                $zhuang_zc_total = 0;
                $xian_total = 0;
                $xian_zc_total = 0;
                $zd_total = 0;
                $xd_total = 0;
                $he_total = 0;
                $sb_total = 0;
                $lucky_six_total = 0;
                $ws = 0;
                $tm = 0;
                $dcyk = 0;
                $wsyk = 0;
                $tmyk = 0;
                $zxyk_zc = 0;
                $zxxm = 0;
                foreach ($betlogs as $item) {
                    $itemtype = $item['type'];
                    $odds_bets = $item['odds'];
                    $agents_share_rate = $item['agents_share_rate'];
                    if ($itemtype == 1) {
                        $zhuang_total += $odds_bets;
                        $zhuang_zc_total += $odds_bets * $agents_share_rate / 10;
                    } elseif ($itemtype == 2) {
                        $xian_total += $odds_bets;
                        $xian_zc_total += $odds_bets * $agents_share_rate / 10;
                    } elseif ($itemtype == 3) {
                        $he_total += $odds_bets;
                        $sb_total += $odds_bets;
                    } elseif ($itemtype == 4) {
                        $zd_total += $odds_bets;
                        $sb_total += $odds_bets;
                    } elseif ($itemtype == 5) {
                        $xd_total += $odds_bets;
                        $sb_total += $odds_bets;
                    } elseif ($itemtype == 7) {
                        $lucky_six_total += $odds_bets;
                    }
                }
                $zhuang_total_last = $zhuang_total - $zhuang_zc_total;
                $xian_total_last = $xian_total - $xian_zc_total;
                $dc_total = min([$zhuang_total_last, $xian_total_last]);
                if ($zhuang_total_last != 0 || $xian_total_last != 0) {
                    if ($mantissa > 0) {
                        $tm = floor(abs($zhuang_total_last - $xian_total_last) / $mantissa) * $mantissa;
                    } elseif ($mantissa == 0) {
                        $tm = abs($zhuang_total_last - $xian_total_last);
                    }
                    if ($tm < $config['odds_zx_min']) {
                        $tm = 0;
                    }
                    $ws = abs($zhuang_total_last - $xian_total_last) - $tm;
                }
            /*    if ($tm >2000) {
                    echo '推码'.$tm.'大于2000，终止执行';
                    exit;
                }*/
                
                if ($cardGame['zhuang'] == 1) {
                    $dcyk = $dc_total - ($dc_total * 0.95);
                }
                if ($zhuang_total_last > $xian_total_last) {
                    if ($cardGame['zhuang'] == 1) {
                        $wsyk = -($ws * 0.95);
                        $tmyk = -($tm * 0.95);
                        $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                    } elseif ($cardGame['zhuang'] == 2) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $zhuang_total_last - $xian_total_last;
                    }
                } elseif ($zhuang_total_last < $xian_total_last) {
                    if ($cardGame['zhuang'] == 1) {
                        $wsyk = $ws;
                        $zxxm = $tm;
                        $tmyk = $tm;
                        $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                    } elseif ($cardGame['zhuang'] == 2) {
                        $wsyk = -$ws;
                        $tmyk = -$tm;
                        $zxyk = $zhuang_total_last - $xian_total_last;
                    }
                }

                if ($cardGame['zhuang'] == 1) {
                    $zxyk_zc = $xian_zc_total - $zhuang_zc_total * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $zxyk_zc = $zhuang_zc_total - $xian_zc_total;
                }
                //统计代理总赢
                $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
                //客户盈亏
                $khyk = -($dlzy + $tmyk);
                //台面筹码
                $tmcm = $dlzy + $khyk;
                
                $rUpdata = [
                    'boots_number'=>$game['boots_number'],
                    'ju'=>$game['ju'],
                    'zhuang_total' => $zhuang_total,
                    'xian_total' => $xian_total,
                    'dc_total' => $dc_total,
                    'ws' => $ws,
                    'tm' => $tm,
                    'dcyk' => $dcyk,
                    'tmyk' => $tmyk,
                    'wsyk' => $wsyk,
                    'zxxm' => $zxxm,
                    'sbyk' => $sbyk,
                    'luckysix_yk' => $luckysix_yk,
                    'dlzy' => $dlzy,
                    'khyk' => $khyk,
                    'tmcm' => $tmcm,
                    'zxyk' => $zxyk,
                    'zxyk_zc' => $zxyk_zc,
                    'zhuang_zc_total' => $zhuang_zc_total,
                    'xian_zc_total' => $xian_zc_total,
                    'card_game_id'=>$card_game_id
                ];
                CardGameReportAdd::create($rUpdata);
                
                /*CardGameReport::where('card_game_id', $card_game_id)->update([
                    'dc_total' => $dc_total,
                    'dcyk' => $dcyk,
                    'ws' => $ws,
                    'tm' => $tm,
                    'wsyk' => $wsyk,
                    'tmyk' => $tmyk,
                    'dlzy' => $dlzy,
                    'khyk' => $khyk,
                    'tmcm' => $tmcm,
                    'zxyk_zc' => $zxyk_zc,
                    'zhuang_zc_total' => $zhuang_zc_total,
                    'xian_zc_total' => $xian_zc_total
                ]);*/

            }

            //修改历史消息
            ChatPacket::where('fromuid', $uid)->where('card_game_id', $card_game_id)->update(['message' => $text]);
            //修改投注表
            $chatPacket = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->field('message')->find();
            $chatPacketArr = json_decode($chatPacket['message'], true);
            $odds_total = 0;
            foreach ($chatPacketArr[0] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item[$type] = $odds;
                    if ($type == 1) {
                        $item['z'] = $odds;
                    } elseif ($type == 2) {
                        $item['x'] = $odds;
                    }
                    $item['win'] = -$odds;
                    $odds_total += $odds;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $odds_total += $item[$type];
                }
                if ($item['key'] == -1) {
                    $item[$type] = $odds_total;
                    if ($type == 1) {
                        $item['z'] = $odds_total;
                    } elseif ($type == 2) {
                        $item['x'] = $odds_total;
                    }
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 3)->update(['message' => json_encode($chatPacketArr)]);
            //修改余分表
            $chatPacketyf = ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->field('message')->find();
            $chatPacketArryf = json_decode($chatPacketyf['message'], true);
            $yf_total = 0;
            $win_total = 0;
            $yf_after = 0;
            if ($game['zhuang'] == 1) {
                $yf_after = $score_before + $win;
            } elseif ($game['zhuang'] == 2) {
                $yf_after = $score_before + $win;
            }
            foreach ($chatPacketArryf['data'] as &$item) {
                if (!empty($item['uid']) && $item['uid'] == $uid) {
                    $item['score'] = intval($yf_after);
                    $item['win'] = $win;
                    $yf_total += $yf_after;
                    $win_total += $win;
                } elseif ($item['key'] != -1 && !empty($item['uid']) && $item['uid'] != $uid) {
                    $yf_total += $item['score'];
                    $win_total += $item['win'];
                }
                if ($item['key'] == -1) {
                    $item['score'] = $yf_total;
                    $item['win'] = $win_total;
                }
            }
            ChatPacket::where('card_game_id', $card_game_id)->where('msgtype', 4)->update(['message' => json_encode($chatPacketArryf)]);
            //修改余分流水表
            $usLog = UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->select();
            $score_after_us1 = 0;
            $us_chanage = 0;
            foreach ($usLog as $v1) {
                if (intval($v1['type']) == intval($type)) {
                    $score_us = $v1['score'];
                    $score_change_us = -$odds;
                    $score_after_us = $score_us - $odds;
                    $score_after_us1 = $score_after_us;
                    $note = $config['mark'] . '桌-' . $game['boots_number'] . '靴-' . $game['ju'] . '局 ' . $text;
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', $type)
                        ->update(['score' => $score_us, 'score_change' => $score_change_us, 'score_after' => $score_after_us, 'note' => $note]);
                }
                if ($v1['type'] == 111) {
                    if ($game['zhuang'] == 1) {
                        $us_chanage = $odds + $odds * 0.95;
                    } elseif ($game['zhuang'] == 2) {
                        $us_chanage = $odds + $odds * 1;
                    }
                    UserScoreLog::where('uid', $uid)->where('card_game_id', $card_game_id)->where('type', 111)
                        ->update(['score' => $score_after_us1, 'score_change' => $us_chanage, 'score_after' => $yf_after]);
                }
            }
            //修改user余分
            User::where('uid', $uid)->update(['score' => $yf_after]);
            //修改积分记录
            $tconfig = TeamConfig::where('tid', $tid)->find();
            $score_rate = $tconfig['score_rate'];
            $integral = 0;
            if ($score_rate > 0) {
                $integral = common::math_div($odds, $score_rate, 2);
            }
            $ilog = IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)->field('integral')->find();
            $cinte = $integral - $ilog['integral'];
            IntegralLog::where('uid', $uid)->where('card_game_id', $card_game_id)
                ->update(['score' => $odds, 'integral' => $integral]);
            $datetime = date('Ymd', time());
            $intedate = IntegralDate::where('uid', $uid)->where('date_time', $datetime)->find();
            $dateinte = $intedate['integral'] + $cinte;
            $integral_total = $intedate['integral_total'] + $cinte;
            IntegralDate::where('uid', $uid)->where('date_time', $datetime)->update(['integral' => $dateinte, 'integral_total' => $integral_total]);
            echo '修改完成';
        } else {
            echo '无下注记录';
        }
    }


    /**
     * @function 计算报表数据
     */
    public function tongjiReport()
    {
        $request = Request::instance();
        $card_game_id = $request->post('card_game_id');
        $report = CardGameReport::where('card_game_id', $card_game_id)->find();
        $khyk = $report['khyk'];
        $zxyk_zc = $report['zxyk_zc'];
        $card_game_id = $report['card_game_id'];
        $sbyk = $report['sbyk'];
        $luckysix_yk = $report['luckysix_yk'];
        // $bets = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
        $win = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->sum('win');
        // echo 'win:' . $win . '||' . 'khyk:' . $khyk . '||zxyk_zc' . $zxyk_zc;
        if ($win - ($khyk - $zxyk_zc) > 10 || $win - ($khyk - $zxyk_zc) < 10) {
            //$report = CardGameReport::where('card_game_id', $card_game_id)->field('sbyk,luckysix_yk')->find();
            $cardGame = CardGame::where('id', $card_game_id)->field('groupid,zhuang,zhuang_dui,xian_dui,lucky_six')->find();
            $config = TeamRoom::where('groupid', $cardGame['groupid'])->field('mantissa,odds_zx_min')->find();
            $mantissa = $config['mantissa'];
            $betlogs = BetsLog::where('card_game_id', $card_game_id)->where('state', 1)->where('user_ai', 0)->select();
            $zhuang_total = 0;
            $zhuang_zc_total = 0;
            $xian_total = 0;
            $xian_zc_total = 0;
            $zd_total = 0;
            $xd_total = 0;
            $he_total = 0;
            $sb_total = 0;
            $lucky_six_total = 0;
            $ws = 0;
            $tm = 0;
            $dcyk = 0;
            $wsyk = 0;
            $tmyk = 0;
            $zxyk = 0;
            $zxxm = 0;
            $zxyk_zc = 0;
            foreach ($betlogs as $item) {
                $type = $item['type'];
                $odds = $item['odds'];
                $agents_share_rate = $item['agents_share_rate'];
                if ($type == 1) {
                    $zhuang_total += $odds;
                    $zhuang_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 2) {
                    $xian_total += $odds;
                    $xian_zc_total += $odds * $agents_share_rate / 10;
                } elseif ($type == 3) {
                    $he_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 4) {
                    $zd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 5) {
                    $xd_total += $odds;
                    $sb_total += $odds;
                } elseif ($type == 7) {
                    $lucky_six_total += $odds;
                }
            }
            $zhuang_total_last = $zhuang_total - $zhuang_zc_total;
            $xian_total_last = $xian_total - $xian_zc_total;
            $dc_total = min([$zhuang_total_last, $xian_total_last]);
            if ($zhuang_total_last != 0 || $xian_total_last != 0) {
                if ($mantissa > 0) {
                    $tm = floor(abs($zhuang_total_last - $xian_total_last) / $mantissa) * $mantissa;
                } elseif ($mantissa == 0) {
                    $tm = abs($zhuang_total_last - $xian_total_last);
                }
                if ($tm < $config['odds_zx_min']) {
                    $tm = 0;
                }
                $ws = abs($zhuang_total_last - $xian_total_last) - $tm;
            }
            if ($cardGame['zhuang'] == 1) {
                $dcyk = $dc_total - ($dc_total * 0.95);
            }
            if ($zhuang_total_last > $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = -($ws * 0.95);
                    $tmyk = -($tm * 0.95);
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            } elseif ($zhuang_total_last < $xian_total_last) {
                if ($cardGame['zhuang'] == 1) {
                    $wsyk = $ws;
                    $zxxm = $tm;
                    $tmyk = $tm;
                    $zxyk = $xian_total_last - $zhuang_total_last * 0.95;
                } elseif ($cardGame['zhuang'] == 2) {
                    $wsyk = -$ws;
                    $tmyk = -$tm;
                    $zxyk = $zhuang_total_last - $xian_total_last;
                }
            }

            if ($cardGame['zhuang'] == 1) {
                $zxyk_zc = $xian_zc_total - $zhuang_zc_total * 0.95;
            } elseif ($cardGame['zhuang'] == 2) {
                $zxyk_zc = $zhuang_zc_total - $xian_zc_total;
            }
            //统计代理总赢
            $dlzy = $wsyk + $sbyk + $dcyk + $luckysix_yk;
            //客户盈亏
            $khyk = -($dlzy + $tmyk);
            //台面筹码
            $tmcm = $dlzy + $khyk;

//            print_r([
//                'dc_total' => $dc_total,
//                'dcyk' => $dcyk,
//                'ws' => $ws,
//                'tm' => $tm,
//                'wsyk' => $wsyk,
//                'tmyk' => $tmyk,
//                'dlzy' => $dlzy,
//                'khyk' => $khyk,
//                'tmcm' => $tmcm,
//                'zxyk_zc' => $zxyk_zc,
//                'zhuang_zc_total' => $zhuang_zc_total,
//                'xian_zc_total' => $xian_zc_total
//            ]);
//            exit;
            CardGameReport::where('card_game_id', $card_game_id)->update([
                'dc_total' => $dc_total,
                'dcyk' => $dcyk,
                'ws' => $ws,
                'tm' => $tm,
                'wsyk' => $wsyk,
                'tmyk' => $tmyk,
                'dlzy' => $dlzy,
                'khyk' => $khyk,
                'tmcm' => $tmcm,
                'zxyk_zc' => $zxyk_zc,
                'zhuang_zc_total' => $zhuang_zc_total,
                'xian_zc_total' => $xian_zc_total
            ]);
            echo 'card_game_id:' . $card_game_id . '数据有误||执行完毕' . PHP_EOL;
        }
        echo 'card_game_id:' . $card_game_id . '牌局数据正常||执行完毕' . PHP_EOL;
        // CardGameReport::where('card_game_id', $card_game_id)->update(['tongji_report' => 1]);

    }


    /**
     * @function 检查数据
     */
    public function checkQilinData()
    {
        $request = Request::instance();
        $begin_time_data = strtotime($request->post('begin_time'));
        $number = $request->post('number');
        $tid =  $request->post('tid');
        $end_time = $begin_time_data+ 3600*$number;
        $begin_time = $begin_time_data + 3600*($number-1);
        // $boots_number = $request->post('boots_number');
        $games = CardGame::where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time)->where('tid',$tid)->select();
        foreach ($games as $item) {
            $card_game_id = $item['id'];
            $bets_merge_win = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
            $user_score_win = UserScoreLog::where('user_ai', 0)->where('tourist', 0)->where('card_game_id', $card_game_id)->sum('score_change');
            $card_report = CardGameReport::where('card_game_id', $card_game_id)->field('khyk,zxyk_zc,dlzy')->find();
            $card_report_win = $card_report['khyk'] - $card_report['zxyk_zc'];
            if ((($bets_merge_win - $card_report_win)>10) or (($bets_merge_win - $user_score_win)>10) or  (($bets_merge_win - $card_report_win)<-10) or (($bets_merge_win - $user_score_win)<-10)) {
                print_r([
                    'card_game_id' => $card_game_id,
                    'bets_merge_win' => $bets_merge_win,
                    'user_score_win' => $user_score_win,
                    'card_report_win' => $card_report_win,
                    'tmcm' => $card_report['khyk'] + $card_report['dlzy']
                ]);

                print_r('======================================================' . PHP_EOL);
            }
        }
        echo '时间：'.date('Y-m-d H:i:s',$begin_time).'--'.date('Y-m-d H:i:s',$end_time).'执行完毕';
    }

    /**
     * @function 检查下注记录
     */
    public function checkBetsLog()
    {
        $request = Request::instance();
       // $begin_time = $request->get('begin_time');
       // $end_time = $request->get('end_time');
        $tid =  $request->get('tid');
        $games = CardGame::where('tongji_bets_log',0)->where('state',2)->where('tid',$tid)->limit(50)->select();
        $countgames = 0;
        $count = 0;
        foreach ($games as $item) {
            $card_game_id = $item['id'];
            $card_report = CardGameReport::where('card_game_id', $card_game_id)->field('khyk,zxyk_zc,dlzy')->find(); 
            if (empty($card_report)) {
                $countgames++;      
                CardGame::where('id', $card_game_id)->update(['tongji_bets_log'=>1]);
            }else {
               
            if ($item['bets_merge_state'] == 1) {
                $bets_merge_win = BetsMerge::where('card_game_id', $card_game_id)->sum('win');
                $card_report_win = $card_report['khyk'] - $card_report['zxyk_zc'];
                $countgames++;          
                $bets_log_win = BetsLog::where('card_game_id', $card_game_id)->where('user_ai', 0)->where('tourist', 0)->sum('win');
                if ( abs($bets_merge_win - $bets_log_win)>5 ) {
                    $count++;
                   $message = [
                        'card_game_id' => $card_game_id,
                        'bets_merge_win' => $bets_merge_win,
                        'card_report_win' => $card_report_win,
                        'bets_log_win' => $bets_log_win,
                    ];    
                   $checklogData = [
                       'card_game_id'=>$card_game_id,
                       'message'=>json_encode($message),
                       'check_type' => 'checkBetsLog',
                       'mktime'=>date('Y-m-d H:i:s')
                   ];
                   CheckLog::create($checklogData);
                }
                CardGame::where('id', $card_game_id)->update(['tongji_bets_log'=>1]);
            }
            }
       
        }
        echo '执行完毕，执行记录数'.$countgames.'错误记录:'.$count;
    }
    
    /**
     * @function 检查流水记录
     */
    public function checkUserLog()
    {
        $request = Request::instance();
       // $begin_time = $request->get('begin_time');
       // $end_time = $request->get('end_time');
        $tid =  $request->get('tid');
        // $boots_number = $request->post('boots_number');
        $games = CardGame::where('tongji_user_log',0)->where('state',2)->where('tid',$tid)->limit(50)->select();
        $countgames = 0;
        $count = 0;
        foreach ($games as $item) {
            $countgames++;
            $card_game_id = $item['id'];
            
            $user_score_count = UserScoreLog::where('user_ai', 0)->where('tourist', 0)->where('card_game_id', $card_game_id)->whereIn('type',[1,2,3,4,5,6,7])->count();

            $bets_log_count = BetsLog::where('card_game_id', $card_game_id)->where('user_ai', 0)->where('tourist', 0)->count();
            if ( $bets_log_count != $user_score_count) {
                $count++;
                $message = [
                    'card_game_id' => $card_game_id,
                    'bets_log_count' => $bets_log_count,
                    'user_score_count' => $user_score_count,
     
                ];
                
                $checklogData = [
                    'card_game_id'=>$card_game_id,
                    'message'=>json_encode($message),
                    'check_type' => 'checkUserLog',
                    'mktime'=>date('Y-m-d H:i:s')
                ];
                CheckLog::create($checklogData);
                
            }
            CardGame::where('id', $card_game_id)->update(['tongji_user_log'=>1]);
        }
        echo '执行完毕，执行记录数'.$countgames.'错误记录:'.$count;
    }
    
    /**
     * @function 检查结算报表
     */
    public function checkReportLog()
    {
        $request = Request::instance();
        //$begin_time = $request->get('begin_time');
        //$end_time = $request->get('end_time');
        $tid =  $request->get('tid');
        $games = CardGame::where('tongji_report_log',0)->where('state',2)->where('tid',$tid)->limit(50)->select();
        $countgames = 0;
        $count = 0;
        foreach ($games as $item) {
            $countgames++;
            $card_game_id = $item['id'];
            $card_report = CardGameReport::where('card_game_id', $card_game_id)->where('tongji_report',1)->field('khyk,zxyk_zc,dlzy')->find();
            if (!empty($card_report)) {                
            $card_report_win = $card_report['khyk'] - $card_report['zxyk_zc'];
            $bets_log_win = BetsLog::where('card_game_id', $card_game_id)->where('user_ai', 0)->where('tourist', 0)->sum('win');
            if ( abs($bets_log_win - $card_report_win)>5 ) {
                $count++;
                $message = [
                    'card_game_id' => $card_game_id,         
                    'card_report_win' => $card_report_win,
                    'bets_log_win' => $bets_log_win,
                ];
                
                $checklogData = [
                    'card_game_id'=>$card_game_id,
                    'message'=>json_encode($message),
                    'check_type' => 'checkReportLog',
                    'mktime'=>date('Y-m-d H:i:s')
                ];
                CheckLog::create($checklogData);
            }
            }
            CardGame::where('id', $card_game_id)->update(['tongji_report_log'=>1]);
        }
        echo '执行完毕，执行记录数'.$countgames.'错误记录:'.$count;
    }
    
    
    
    
    public function shanData()
    {
        $data = BetsLog::field('card_game_id,uid')->where('uid', 7235)->where('time', '<', 1640275200)->select();
        foreach ($data as $v) {
            $betsData = BetsMerge::where('card_game_id', $v['card_game_id'])->where('uid', $v['uid'])->find();
            if (empty($betsData)) {
                echo $v['card_game_id'] . '|' . PHP_EOL;
            }
        }
        echo '执行完成';
    }

    public function shanTongji()
    {
        $card_game = CardGame::whereIn('id', [371094, 371093, 370977, 370921, 369851, 369813, 369361, 369360, 369356, 369347, 369322])->field('id,room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,groupid,mktime,super_he,fb,lq,tid')->select();
        if (!empty($card_game)) {
            foreach ($card_game as $item_card_game) {
                $card_game_id = $item_card_game['id'];
                $zhuang_win = $item_card_game['zhuang'];
                $zhuang_dui = $item_card_game['zhuang_dui'];
                $xian_dui = $item_card_game['xian_dui'];
                $lucky_six = $item_card_game['lucky_six'];
                $super_he = $item_card_game['super_he'];
                $fb = $item_card_game['fb'];
                $lq = $item_card_game['lq'];
                $room_id = $item_card_game['room_id'];
                $boots_number = $item_card_game['boots_number'];
                $ju = $item_card_game['ju'];
                $groupid = $item_card_game['groupid'];
                $mktime = $item_card_game['mktime'];
                $tid = $item_card_game['tid'];
                //获取赔率
                $query_sql = TeamRoom::alias('t')->join('team_config c', 't.tid=c.tid');
                $query_sql->where('t.groupid', $groupid);
                $query_sql->field('t.zhuang,t.xian,t.zhuang_dui,t.xian_dui,t.he,t.lq,t.fb,t.super_he,t.lucky_six_12,t.lucky_six_20,c.score_rate');
                $system_info = $query_sql->find();
                $zhuang_rate = $system_info['zhuang'];
                $xian_rate = $system_info['xian'];
                $lucky_six_12 = $system_info['lucky_six_12'];
                $lucky_six_20 = $system_info['lucky_six_20'];
                $he_rate = $system_info['he'];
                $zhuang_dui_rate = $system_info['zhuang_dui'];
                $xian_dui_rate = $system_info['xian_dui'];
                $lq_rate = $system_info['lq'];
                $fb_rate = $system_info['fb'];
                $super_he_rate = $system_info['super_he'];
                $score_rate = $system_info['score_rate'];//流水比积分
                //合并下注记录
                $betsData = [];
                $betsAll = [
                    'type1' => 0,
                    'type2' => 0,
                    'type3' => 0,
                    'type4' => 0,
                    'type5' => 0,
                    'type6' => 0,
                    'type7' => 0,
                    'type8' => 0,//龙七
                    'type9' => 0,//凤八
                    'type10' => 0,//超和
                ];
                $betsLog = BetsLog::field('odds,type,uid,xm_type,xm_rate,agents_share_rate,agents_id,score_before,ip,tid,mark,groupname')
                    ->where('card_game_id', $card_game_id)->where('uid', 7235)->where('user_ai', 0)->where('tourist', 0)->where('state', 1)->select();
                if (!empty($betsLog)) {
                    foreach ($betsLog as $bets_log_item) {
                        $bets_log_uid = $bets_log_item['uid'];
                        $bets_log_type = 'type' . $bets_log_item['type'];
                        $bets_log_odds = $bets_log_item['odds'];
                        $betsAll[$bets_log_type] += $bets_log_odds;  //下注总额
                        if (empty($betsData[$bets_log_uid])) {
                            $betsData[$bets_log_uid] = [
                                'uid' => $bets_log_uid,
                                'type1' => 0,
                                'type2' => 0,
                                'type3' => 0,
                                'type4' => 0,
                                'type5' => 0,
                                'type6' => 0,
                                'type7' => 0,
                                'type8' => 0,//龙七
                                'type9' => 0,//凤八
                                'type10' => 0,//超和
                                'xm_type' => $bets_log_item['xm_type'],
                                'xm_rate' => $bets_log_item['xm_rate'],
                                'agents_share_rate' => $bets_log_item['agents_share_rate'],
                                'agents_id' => $bets_log_item['agents_id'],
                                'score_before' => $bets_log_item['score_before'],
                                'ip' => $bets_log_item['ip'],
                                'tid' => $bets_log_item['tid'],
                                'mark' => $bets_log_item['mark'],
                                'groupname' => $bets_log_item['groupname']
                            ];
                            $betsData[$bets_log_uid][$bets_log_type] = $bets_log_odds;
                        } else {
                            $betsData[$bets_log_uid][$bets_log_type] += $bets_log_odds;
                        }
                    }

                    //写入合并后的记录
                    foreach ($betsData as $bets_data_item) {
                        $uid = $bets_data_item['uid'];
                        $user_info = User::alias('u')->join('agents a', 'u.agents_id=a.agents_id')->where('u.uid', $uid)
                            ->field('u.tourist,u.ai,u.name,u.extra_share,u.xm_rate,u.agents_share_rate,u.agents_sb_share_rate,u.xm_type,u.username,a.name as agents_name,a.agents_id,a.account as agents_account')->find();
                        $user_zx_xm_dan = 0;//单边庄闲洗码
                        $user_zx_xm = 0;//庄闲洗码
                        $user_sb_xm = 0;//三宝洗码
                        $user_lucky_xm = 0;//幸运六洗码
                        $user_super_he_xm = 0;//超和洗码
                        $user_lq_xm = 0;//龙七洗码
                        $user_fb_xm = 0;//凤八洗码
                        $user_zx_losewin_yx = 0;//庄闲有效输赢
                        $user_zx_losewin = 0;//庄闲输赢
                        $user_sb_losewin = 0;//三宝输赢
                        $user_lucky_losewin = 0;//幸运六输赢
                        $user_super_he_losewin = 0;//超和洗码
                        $user_lq_losewin = 0;//龙七洗码
                        $user_fb_losewin = 0;//凤八洗码
                        $zhuang_yx = 0;//有效庄注
                        $xian_yx = 0;//有效闲注
                        if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                            $zhuang_yx = $bets_data_item['type1'] - $bets_data_item['type2'];
                        }
                        if ($bets_data_item['type2'] > $bets_data_item['type1']) {
                            $xian_yx = $bets_data_item['type2'] - $bets_data_item['type1'];
                        }
                        //庄闲单边洗码
                        if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                            if ($zhuang_win == 2) {
                                $user_zx_xm_dan += ($bets_data_item['type1'] - $bets_data_item['type2']);
                            }
                        } elseif ($bets_data_item['type2'] > $bets_data_item['type1']) {
                            if ($zhuang_win == 1) {
                                $user_zx_xm_dan += ($bets_data_item['type2'] - $bets_data_item['type1']);
                            }
                        }
                        if ($user_info['xm_type'] == 1) {
                            if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                                if ($zhuang_win == 2) {
                                    $user_zx_xm += ($bets_data_item['type1'] - $bets_data_item['type2']);
                                }
                            }
                            if ($bets_data_item['type2'] > $bets_data_item['type1']) {
                                if ($zhuang_win == 1) {
                                    $user_zx_xm += ($bets_data_item['type2'] - $bets_data_item['type1']);
                                }
                            }
                        } elseif ($user_info['xm_type'] == 2) {
                            if ($zhuang_win != 3) {
                                $user_zx_xm = abs($bets_data_item['type1'] - $bets_data_item['type2']);
                            }
                        }
                        if ($zhuang_win < 3) {
                            $user_sb_xm += $bets_data_item['type3'];
                        }
                        if ($zhuang_dui == 0) {
                            $user_sb_xm += $bets_data_item['type4'];
                        }
                        if ($xian_dui == 0) {
                            $user_sb_xm += $bets_data_item['type5'];
                        }
                        if ($lucky_six == 0) {
                            $user_lucky_xm += $bets_data_item['type7'];
                        }
                        if ($lq == 0) {
                            $user_lq_xm += $bets_data_item['type8'];
                        }
                        if ($fb == 0) {
                            $user_fb_xm += $bets_data_item['type9'];
                        }
                        if ($super_he == 0) {
                            $user_super_he_xm += $bets_data_item['type10'];
                        }

                        if ($zhuang_win != 3) {
                            if ($zhuang_win == 1) {
                                $user_zx_losewin_yx += common::math_mul($zhuang_yx, $zhuang_rate, 2);
                                $user_zx_losewin += common::math_mul($bets_data_item['type1'], $zhuang_rate, 2);
                            } else {
                                $user_zx_losewin_yx += -$zhuang_yx;
                                $user_zx_losewin += -$bets_data_item['type1'];
                            }
                            if ($zhuang_win == 2) {
                                $user_zx_losewin_yx += $xian_yx;
                                $user_zx_losewin += $bets_data_item['type2'];
                            } else {
                                $user_zx_losewin_yx += -$xian_yx;
                                $user_zx_losewin += -$bets_data_item['type2'];
                            }
                        }
                        if ($zhuang_win == 3) {
                            $user_sb_losewin += $bets_data_item['type3'] * $he_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type3'];
                        }
                        if ($zhuang_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type4'] * $zhuang_dui_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type4'];
                        }
                        if ($xian_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type5'] * $xian_dui_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type5'];
                        }
                        if ($lucky_six == 0) {
                            $user_lucky_losewin += -$bets_data_item['type7'];
                        } elseif ($lucky_six == 6) {
                            $user_lucky_losewin += $bets_data_item['type7'] * $lucky_six_12;
                        } elseif ($lucky_six == 7) {
                            $user_lucky_losewin += $bets_data_item['type7'] * $lucky_six_20;
                        }
                        if ($lq > 0) {
                            $user_lq_losewin += $bets_data_item['type8'] * $lq_rate;
                        } else {
                            $user_lq_losewin += -$bets_data_item['type8'];
                        }
                        if ($fb > 0) {
                            $user_fb_losewin += $bets_data_item['type9'] * $fb_rate;
                        } else {
                            $user_fb_losewin += -$bets_data_item['type9'];
                        }
                        if ($super_he > 0) {
                            $user_super_he_losewin += $bets_data_item['type10'] * $super_he_rate;
                        } else {
                            $user_super_he_losewin += -$bets_data_item['type10'];
                        }

                        $win_lose = $user_zx_losewin + $user_sb_losewin + $user_lucky_losewin + $user_lq_losewin + $user_fb_losewin + $user_super_he_losewin;
                        //获取结算后余分
                        $user_score_log_ukey = $groupid . '_' . $card_game_id . '_' . $bets_log_uid . '_111';
                        $user_score_after = $bets_data_item['score_before'] + $win_lose;
                        $user_score_log_data = UserScoreLog::where('ukey', $user_score_log_ukey)->field('score_after')->find();
                        if (!empty($user_score_log_data['score_after'])) {
                            $user_score_after = $user_score_log_data['score_after'];
                        }
                        if ($user_info['ai'] == 0 && $user_info['tourist'] == 0) {
                            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid;
                            $find_ukey = BetsMerge::where('ukey', $bets_ukey)->field('id')->find();
                            if (!empty($find_ukey)) {
                                $bets_merge_data = [
                                    'uid' => $uid,
                                    'nickname' => $user_info['name'],
                                    'zhuang' => $bets_data_item['type1'],
                                    'xian' => $bets_data_item['type2'],
                                    'he' => $bets_data_item['type3'],
                                    'zhuang_dui' => $bets_data_item['type4'],
                                    'xian_dui' => $bets_data_item['type5'],
                                    'lucky_six' => $bets_data_item['type7'],
                                    'lq' => $bets_data_item['type8'],
                                    'fb' => $bets_data_item['type9'],
                                    'super_he' => $bets_data_item['type10'],
                                    'score_before' => $bets_data_item['score_before'],
                                    'score_after' => $user_score_after,
                                    'win' => $win_lose,
                                    'game_zhuang' => $zhuang_win,
                                    'game_zhuang_dui' => $zhuang_dui,
                                    'game_xian_dui' => $xian_dui,
                                    'game_lucky_six' => $lucky_six,
                                    'game_lq' => $lq,
                                    'game_fb' => $fb,
                                    'game_super_he' => $super_he,
                                    'state' => 0,
                                    'mktime' => $mktime,
                                    'card_game_id' => $card_game_id,
                                    'room_id' => $room_id,
                                    'boots_number' => $boots_number,
                                    'ju' => $ju,
                                    'groupid' => $groupid,
                                    'agents_id' => $user_info['agents_id'],
                                    'agents_account' => $user_info['agents_account'],
                                    'agents_name' => $user_info['agents_name'],
                                    'username' => $user_info['username'],
                                    'user_zx_xm_dan' => $user_zx_xm_dan,
                                    'user_zx_xm' => $user_zx_xm,
                                    'user_sb_xm' => $user_sb_xm,
                                    'user_lucky_xm' => $user_lucky_xm,
                                    'user_lq_xm' => $user_lq_xm,
                                    'user_fb_xm' => $user_fb_xm,
                                    'user_super_he_xm' => $user_super_he_xm,
                                    'user_zx_losewin' => $user_zx_losewin,
                                    'user_sb_losewin' => $user_sb_losewin,
                                    'user_lucky_losewin' => $user_lucky_losewin,
                                    'user_lq_losewin' => $user_lq_losewin,
                                    'user_fb_losewin' => $user_fb_losewin,
                                    'user_super_he_losewin' => $user_super_he_losewin,
                                    'xm_type' => $user_info['xm_type'],
                                    'agents_share_rate' => $user_info['agents_share_rate'],
                                    'agents_sb_share_rate' => $user_info['agents_sb_share_rate'],
                                    'xm_rate' => $user_info['xm_rate'],
                                    'zhuang_yx' => $zhuang_yx,
                                    'xian_yx' => $xian_yx,
                                    'zhuang_xian_dc' => min($bets_data_item['type1'], $bets_data_item['type2']),
                                    'user_zx_losewin_yx' => $user_zx_losewin_yx,
                                    'extra_share' => $user_info['extra_share'],
                                    'extra_share_score' => intval($win_lose * $user_info['extra_share'] / 100),
                                    'ukey' => $bets_ukey,
                                    'game_type' => 0,
                                    'ip' => $bets_data_item['ip'],
                                    'tid' => $bets_data_item['tid'],
                                    'mark' => $bets_data_item['mark'],
                                    'groupname' => $bets_log_item['groupname']
                                ];
                                BetsMerge::create($bets_merge_data);
                            }
                        }
                    }
                }
                echo '牌局ID' . $card_game_id . '执行完毕_' . date('Y-m-d H:i:s') . PHP_EOL;
            }
        } else {
            echo '暂无牌局记录_' . date('Y-m-d H:i:s') . PHP_EOL;
        }
    }

    /**
     * @return void 检查下注表
     */
    public function checkBets()
    {
        $request = Request::instance();
        $uid = $request->post('uid');
        $user_sql = User::where('ai', 0)->where('tourist', 0)->field('uid');
        if (intval($uid) > 0) {
            $user_sql->where('uid', $uid);
        }
        $user = $user_sql->select();
        foreach ($user as $item) {
            $uid = $item['uid'];
            $data = BetsMerge::where('uid', $uid)->field('id,user_sb_losewin,user_lucky_losewin,win,user_zx_losewin')->where('mktime', '>=', 1638288000)->where('mktime', '<=', 1640966399)->select();
            foreach ($data as $v) {
                $win = $v['user_sb_losewin'] + $v['user_lucky_losewin'] + $v['user_zx_losewin'] - $v['win'];
                if ($win > 10 or $win < -10) {
                    echo 'bets_merge_id:' . $v['id'] . PHP_EOL;
                }
            }
        }
        echo '执行完毕';
    }

    /**
     * @function 修改unionid
     */
    public function updateUnionid()
    {
        $data = User::where('tid', 584)->field('unionid,uid,openid')->select();
        foreach ($data as $v) {
            User::where('uid', $v['uid'])->update(['unionid' => $v['unionid'] . '_584', 'openid' => $v['openid'] . '_584']);
        }
        echo '执行完毕';
    }

    
    /**
     * @function 9613 每日客人输赢
     */
    
    public function winlose9613() {
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $tid = 8;
        if (empty($begin_time) || empty($end_time)) {
            echo '开始和结束时间都不能为空';exit();
        }
        $uids = BetsMerge::field('uid')->where('tid',$tid)->where('mktime','>=',strtotime($begin_time))->where('mktime','<=',strtotime($end_time))->group('uid')->select();
        $people_total = 0;
        $win_total = 0;
        foreach ($uids as $uiditem){
            $uid = $uiditem['uid'];
            $win = BetsMerge::where('uid',$uid)->where('tid',$tid)->where('mktime','>=',strtotime($begin_time))->where('mktime','<=',strtotime($end_time))->sum('win');
            $people_total++;
            $win_total +=$win;
            echo '用户id：'.$uid.',时间：'.$begin_time.'-'.$end_time.',输赢数：'.$win.PHP_EOL;         
        }
        echo '合计：'.$people_total.'人,'.'总输赢数:'.$win_total.PHP_EOL;
    }

    /*
     * @function 检测数据库主从复制
     */
    
    public function domysqlzhucong(){
        $bakArr = ['154.23.221.76:8881','27.124.44.146:8882','47.102.147.151:8883','82.23.246.149:8884'];
        $echostr = '';
        foreach ($bakArr as $item){
            $data  = file_get_contents('http://'.$item.'/v1/data/mysqlzhucong');
            $echostr .= $data.PHP_EOL;
        }
        echo $echostr;
        exit();

    }
    
    /**
     * @function 检测数据库主从复制
     */
    public function mysqlzhucong() {
       $data =  UserScoreLog::order('id','desc')->limit(1)->field('time')->find();
       $checklogCount = CheckLog::where('state',0)->count();
       $flag = '没有脏数据';
       if ($checklogCount>0) {
           $flag = '存在脏数据';
       }
       echo config("database.database").'最后一条记录时间是：'.date('Y-m-d H:i:s',$data['time']).$flag.PHP_EOL;
       exit();
    }



}

